<?php
/**
 * Veritabanı Bağlantısı ve Yardımcı İşlemler
 * PDO tabanlı MySQL ve SQLite desteği
 */

require_once __DIR__ . '/../config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $driver = DB_DRIVER;

    if ($driver === 'mysql' && extension_loaded('pdo_mysql')) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ]);
            return $pdo;
        } catch (PDOException $e) {
            // MySQL bağlantısı kurulamazsa ve SQLite sürücüsü varsa SQLite'a geçiş yap
            error_log("MySQL bağlantı hatası: " . $e->getMessage() . " -> SQLite moduna geçiliyor.");
            $driver = 'sqlite';
        }
    }

    // SQLite Bağlantısı ve Otomatik Şema Kurulumu
    $dbFile = SQLITE_PATH;
    $dir = dirname($dbFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $isNew = !file_exists($dbFile) || filesize($dbFile) === 0;

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // SQLite yabancı anahtar kısıtlamalarını etkinleştir
    $pdo->exec('PRAGMA foreign_keys = ON;');

    if ($isNew) {
        init_sqlite_schema($pdo);
    }

    return $pdo;
}

/**
 * SQLite için tabloları ve varsayılan verileri oluşturur
 */
function init_sqlite_schema(PDO $pdo): void {
    $schema = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'staff',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        code TEXT,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        warehouse_name TEXT NOT NULL DEFAULT 'Ana Depo',
        code TEXT,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_person TEXT,
        phone TEXT,
        email TEXT,
        tax_number TEXT,
        address TEXT,
        notes TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_person TEXT,
        phone TEXT,
        email TEXT,
        tax_number TEXT,
        address TEXT,
        notes TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku TEXT NOT NULL UNIQUE,
        barcode TEXT UNIQUE,
        name TEXT NOT NULL,
        category_id INTEGER,
        location_id INTEGER,
        unit TEXT NOT NULL DEFAULT 'Adet',
        purchase_price REAL NOT NULL DEFAULT 0.00,
        sale_price REAL NOT NULL DEFAULT 0.00,
        stock_quantity REAL NOT NULL DEFAULT 0.00,
        min_stock_level REAL NOT NULL DEFAULT 5.00,
        description TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS stock_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        movement_type TEXT NOT NULL,
        quantity REAL NOT NULL,
        unit_price REAL NOT NULL DEFAULT 0.00,
        previous_stock REAL NOT NULL DEFAULT 0.00,
        current_stock REAL NOT NULL DEFAULT 0.00,
        reference_no TEXT,
        supplier_id INTEGER,
        customer_id INTEGER,
        from_location_id INTEGER,
        to_location_id INTEGER,
        movement_date TEXT NOT NULL,
        note TEXT,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );
SQL;

    $pdo->exec($schema);

    // Kullanıcıları ekle
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $seed_users = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, email, password_hash, full_name, role, is_active) VALUES
    (1, 'admin', 'admin@superdepo.local', ?, 'Sistem Yöneticisi', 'admin', 1),
    (2, 'depocu', 'depo@superdepo.local', ?, 'Ahmet Depocu', 'manager', 1)");
    $seed_users->execute([$hash, $hash]);

    // Başlangıç verilerini ekle
    $seed = <<<SQL
    INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES
    ('company_name', 'Süper Depo Yönetimi'),
    ('currency', '₺'),
    ('currency_code', 'TRY'),
    ('date_format', 'd.m.Y'),
    ('low_stock_notification', '1');

    INSERT OR IGNORE INTO categories (id, name, code, description) VALUES
    (1, 'Elektronik & Bilgisayar', 'KAT-ELK', 'Teknoloji, donanım ve bilgisayar parçaları'),
    (2, 'Ofis Kırtasiye & Sarf', 'KAT-OFS', 'Kağıt, toner ve ofis ihtiyaçları'),
    (3, 'Ambalaj & Paketleme', 'KAT-AMB', 'Koli, bant ve paketleme malzemeleri'),
    (4, 'Temizlik & Hijyen', 'KAT-TMZ', 'Depo ve ofis temizlik gereçleri'),
    (5, 'Hırdavat & El Aletleri', 'KAT-HRD', 'El aletleri ve montaj ekipmanları');

    INSERT OR IGNORE INTO locations (id, name, warehouse_name, code, description) VALUES
    (1, 'A-01-01', 'Ana Depo', 'LOC-A1', 'A Koridoru, Raf 1, Göz 1'),
    (2, 'A-01-02', 'Ana Depo', 'LOC-A2', 'A Koridoru, Raf 1, Göz 2'),
    (3, 'B-02-01', 'Ana Depo', 'LOC-B1', 'B Koridoru, Raf 2, Göz 1'),
    (4, 'B-02-02', 'Ana Depo', 'LOC-B2', 'B Koridoru, Raf 2, Göz 2'),
    (5, 'C-03-01', 'Soğuk Depo', 'LOC-C1', 'Soğuk Hava Bölümü, Raf 3');

    INSERT OR IGNORE INTO suppliers (id, name, contact_person, phone, email, tax_number, address, notes) VALUES
    (1, 'Mega Dağıtım A.Ş.', 'Mehmet Yılmaz', '0212 555 10 20', 'siparis@megadagitim.com', '1234567890', 'İkitelli OSB No: 12 Başakşehir / İstanbul', 'Elektronik ana tedarikçisi'),
    (2, 'Birlik Kağıt ve Ambalaj San.', 'Ayşe Demir', '0216 444 30 40', 'info@birlikambalaj.com', '9876543210', 'Dudullu Sanayi Sitesi Ümraniye / İstanbul', 'Ambalaj ve koli tedarikçisi');

    INSERT OR IGNORE INTO customers (id, name, contact_person, phone, email, tax_number, address, notes) VALUES
    (1, 'Atlas Teknoloji Ltd. Şti.', 'Kemal Şahin', '0212 333 44 55', 'satis@atlastekno.com', '4567891230', 'Levent Mah. Cömert Sok. Beşiktaş / İstanbul', 'Kurumsal toptan alıcı'),
    (2, 'Güneş Perakende Mağazaları', 'Zeynep Kaya', '0312 222 11 00', 'merkez@gunesperakende.com', '6543219870', 'Çankaya / Ankara', 'Şube siparişleri');

    INSERT OR IGNORE INTO products (id, sku, barcode, name, category_id, location_id, unit, purchase_price, sale_price, stock_quantity, min_stock_level, description) VALUES
    (1, 'PRD-1001', '8690123456789', 'Kablosuz Optik Fare (Ergonomik)', 1, 1, 'Adet', 180.00, 290.00, 45.00, 10.00, '2.4 GHz USB alıcılı kablosuz fare'),
    (2, 'PRD-1002', '8690123456790', 'Mekanik Klavye Türkçe Q', 1, 1, 'Adet', 450.00, 750.00, 18.00, 5.00, 'Mavi anahtarlı RGB aydınlatmalı mekanik klavye'),
    (3, 'PRD-1003', '8690123456791', 'A4 Fotokopi Kağıdı 80gr (Koli/5 Paket)', 2, 2, 'Koli', 420.00, 550.00, 4.00, 10.00, 'Kritik stok seviyesinde ofis kağıdı kolisi'),
    (4, 'PRD-1004', '8690123456792', 'Koli Bandı 45mm x 100m Şeffaf', 3, 3, 'Adet', 25.00, 40.00, 120.00, 20.00, 'Güçlü yapışkanlı paketleme bandı'),
    (5, 'PRD-1005', '8690123456793', 'Mukavva Koli 40x30x30 cm', 3, 3, 'Adet', 18.50, 30.00, 85.00, 30.00, 'Çift dalga dayanıklı taşıma kolisi'),
    (6, 'PRD-1006', '8690123456794', 'Endüstriyel Sıvı Sabun 5 Lt', 4, 4, 'Adet', 90.00, 140.00, 2.00, 5.00, 'Depo personeli lavabo temizliği'),
    (7, 'PRD-1007', '8690123456795', 'Şarjlı Vidalama Seti 18V', 5, 5, 'Adet', 1100.00, 1650.00, 8.00, 3.00, '2 adet akülü profesyonel set');

    INSERT OR IGNORE INTO stock_movements (id, product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, supplier_id, customer_id, movement_date, note, user_id) VALUES
    (1, 1, 'in', 50.00, 180.00, 0.00, 50.00, 'IRS-2026-001', 1, NULL, '2026-10-20', 'İlk parti mal kabul girişi', 1),
    (2, 1, 'out', 5.00, 290.00, 50.00, 45.00, 'SEVK-2026-015', NULL, 1, '2026-10-22', 'Atlas Teknoloji sevkiyatı', 1),
    (3, 2, 'in', 20.00, 450.00, 0.00, 20.00, 'IRS-2026-002', 1, NULL, '2026-10-21', 'Tedarikçi faturası ile mal kabul', 1),
    (4, 2, 'out', 2.00, 750.00, 20.00, 18.00, 'SEVK-2026-016', NULL, 2, '2026-10-23', 'Güneş Perakende mağaza sevki', 1),
    (5, 3, 'in', 10.00, 420.00, 0.00, 10.00, 'IRS-2026-003', 2, NULL, '2026-10-15', 'Kağıt stoğu girişi', 1),
    (6, 3, 'out', 6.00, 550.00, 10.00, 4.00, 'SEVK-2026-017', NULL, 1, '2026-10-25', 'Ofis ve müşteri teslimatı (Düşük stok kaldı)', 1);
SQL;

    $pdo->exec($seed);
}
