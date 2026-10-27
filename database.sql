-- Super Depo Script - MySQL Veritabanı Şeması ve Örnek Veriler
-- Karakter seti: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Kullanıcılar Tablosu
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Kategoriler Tablosu
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Depo ve Raf Konumları Tablosu
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL, -- örn. "A Blok - Raf 3 - Kat 2"
    warehouse_name VARCHAR(100) NOT NULL DEFAULT 'Ana Depo',
    code VARCHAR(50) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tedarikçiler Tablosu
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    tax_number VARCHAR(50) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Müşteriler Tablosu
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    tax_number VARCHAR(50) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ürünler Tablosu
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    category_id INT NULL,
    location_id INT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'Adet',
    purchase_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    sale_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    stock_quantity DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    min_stock_level DECIMAL(12, 2) NOT NULL DEFAULT 5.00,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    INDEX idx_products_sku (sku),
    INDEX idx_products_barcode (barcode),
    INDEX idx_products_category (category_id),
    INDEX idx_products_stock (stock_quantity, min_stock_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Stok Hareketleri Tablosu (Giriş, Çıkış, Transfer, Düzeltme)
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'transfer', 'adjustment') NOT NULL,
    quantity DECIMAL(12, 2) NOT NULL,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    previous_stock DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    current_stock DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    reference_no VARCHAR(100) NULL, -- İrsaliye, fatura veya sayım fişi numarası
    supplier_id INT NULL,
    customer_id INT NULL,
    from_location_id INT NULL,
    to_location_id INT NULL,
    movement_date DATE NOT NULL,
    note TEXT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movements_date (movement_date),
    INDEX idx_movements_type (movement_type),
    INDEX idx_movements_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Sistem Ayarları Tablosu
CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Denetim İzi ve İşlem Günlüğü (Audit Logs)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Ayarlar
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Süper Depo Yönetimi'),
('currency', '₺'),
('currency_code', 'TRY'),
('date_format', 'd.m.Y'),
('low_stock_notification', '1');

-- Örnek Yönetici Kullanıcısı (Şifre: admin123)
-- Hash: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES
('admin', 'admin@superdepo.local', '$2y$12$xDiy2s6d057IQdHdEW5bUurpr6C0p1RgO8WKIjvKxeV6gyS6yByg.', 'Sistem Yöneticisi', 'admin', 1),
('depocu', 'depo@superdepo.local', '$2y$12$xDiy2s6d057IQdHdEW5bUurpr6C0p1RgO8WKIjvKxeV6gyS6yByg.', 'Ahmet Depocu', 'manager', 1);

-- Örnek Kategoriler
INSERT INTO categories (name, code, description) VALUES
('Elektronik & Bilgisayar', 'KAT-ELK', 'Teknoloji, donanım ve bilgisayar parçaları'),
('Ofis Kırtasiye & Sarf', 'KAT-OFS', 'Kağıt, toner ve ofis ihtiyaçları'),
('Ambalaj & Paketleme', 'KAT-AMB', 'Koli, bant ve paketleme malzemeleri'),
('Temizlik & Hijyen', 'KAT-TMZ', 'Depo ve ofis temizlik gereçleri'),
('Hırdavat & El Aletleri', 'KAT-HRD', 'El aletleri ve montaj ekipmanları');

-- Örnek Depo Konumları
INSERT INTO locations (name, warehouse_name, code, description) VALUES
('A-01-01', 'Ana Depo', 'LOC-A1', 'A Koridoru, Raf 1, Göz 1'),
('A-01-02', 'Ana Depo', 'LOC-A2', 'A Koridoru, Raf 1, Göz 2'),
('B-02-01', 'Ana Depo', 'LOC-B1', 'B Koridoru, Raf 2, Göz 1'),
('B-02-02', 'Ana Depo', 'LOC-B2', 'B Koridoru, Raf 2, Göz 2'),
('C-03-01', 'Soğuk Depo', 'LOC-C1', 'Soğuk Hava Bölümü, Raf 3');

-- Örnek Tedarikçiler
INSERT INTO suppliers (name, contact_person, phone, email, tax_number, address, notes) VALUES
('Mega Dağıtım A.Ş.', 'Mehmet Yılmaz', '0212 555 10 20', 'siparis@megadagitim.com', '1234567890', 'İkitelli OSB No: 12 Başakşehir / İstanbul', 'Elektronik ana tedarikçisi'),
('Birlik Kağıt ve Ambalaj San.', 'Ayşe Demir', '0216 444 30 40', 'info@birlikambalaj.com', '9876543210', 'Dudullu Sanayi Sitesi Ümraniye / İstanbul', 'Ambalaj ve koli tedarikçisi');

-- Örnek Müşteriler
INSERT INTO customers (name, contact_person, phone, email, tax_number, address, notes) VALUES
('Atlas Teknoloji Ltd. Şti.', 'Kemal Şahin', '0212 333 44 55', 'satis@atlastekno.com', '4567891230', 'Levent Mah. Cömert Sok. Beşiktaş / İstanbul', 'Kurumsal toptan alıcı'),
('Güneş Perakende Mağazaları', 'Zeynep Kaya', '0312 222 11 00', 'merkez@gunesperakende.com', '6543219870', 'Çankaya / Ankara', 'Şube siparişleri');

-- Örnek Ürünler
INSERT INTO products (sku, barcode, name, category_id, location_id, unit, purchase_price, sale_price, stock_quantity, min_stock_level, description) VALUES
('PRD-1001', '8690123456789', 'Kablosuz Optik Fare (Ergonomik)', 1, 1, 'Adet', 180.00, 290.00, 45.00, 10.00, '2.4 GHz USB alıcılı kablosuz fare'),
('PRD-1002', '8690123456790', 'Mekanik Klavye Türkçe Q', 1, 1, 'Adet', 450.00, 750.00, 18.00, 5.00, 'Mavi anahtarlı RGB aydınlatmalı mekanik klavye'),
('PRD-1003', '8690123456791', 'A4 Fotokopi Kağıdı 80gr (Koli/5 Paket)', 2, 2, 'Koli', 420.00, 550.00, 4.00, 10.00, 'Kritik stok seviyesinde ofis kağıdı kolisi'),
('PRD-1004', '8690123456792', 'Koli Bandı 45mm x 100m Şeffaf', 3, 3, 'Adet', 25.00, 40.00, 120.00, 20.00, 'Güçlü yapışkanlı paketleme bandı'),
('PRD-1005', '8690123456793', 'Mukavva Koli 40x30x30 cm', 3, 3, 'Adet', 18.50, 30.00, 85.00, 30.00, 'Çift dalga dayanıklı taşıma kolisi'),
('PRD-1006', '8690123456794', 'Endüstriyel Sıvı Sabun 5 Lt', 4, 4, 'Adet', 90.00, 140.00, 2.00, 5.00, 'Depo personeli lavabo temizliği'),
('PRD-1007', '8690123456795', 'Şarjlı Vidalama Seti 18V', 5, 5, 'Adet', 1100.00, 1650.00, 8.00, 3.00, '2 adet akülü profesyonel set');

-- Örnek Stok Hareketleri
INSERT INTO stock_movements (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, supplier_id, customer_id, movement_date, note, user_id) VALUES
(1, 'in', 50.00, 180.00, 0.00, 50.00, 'IRS-2026-001', 1, NULL, '2026-10-20', 'İlk parti mal kabul girişi', 1),
(1, 'out', 5.00, 290.00, 50.00, 45.00, 'SEVK-2026-015', NULL, 1, '2026-10-22', 'Atlas Teknoloji sevkiyatı', 1),
(2, 'in', 20.00, 450.00, 0.00, 20.00, 'IRS-2026-002', 1, NULL, '2026-10-21', 'Tedarikçi faturası ile mal kabul', 1),
(2, 'out', 2.00, 750.00, 20.00, 18.00, 'SEVK-2026-016', NULL, 2, '2026-10-23', 'Güneş Perakende mağaza sevki', 1),
(3, 'in', 10.00, 420.00, 0.00, 10.00, 'IRS-2026-003', 2, NULL, '2026-10-15', 'Kağıt stoğu girişi', 1),
(3, 'out', 6.00, 550.00, 10.00, 4.00, 'SEVK-2026-017', NULL, 1, '2026-10-25', 'Ofis ve müşteri teslimatı (Düşük stok kaldı)', 1);
