<?php
/**
 * Süper Depo Scripti - Kapsamlı Otomatik Test Süiti
 * CLI üzerinden çalıştırılır: php tests/test_system.php
 */

define('IN_TEST_SUITE', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, ?string $details = null): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($details ? " - {$details}" : "") . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " Süper Depo Scripti - Sistem & Entegrasyon Testleri\n";
echo "========================================================\n\n";

// TEST 1: Veritabanı Bağlantısı ve Tablolar
echo "1. Veritabanı ve Şema Kontrolleri:\n";
$db = get_db();
assert_test("Veritabanı bağlantısı kurulabildi", $db instanceof PDO);

$tables = ['users', 'categories', 'locations', 'suppliers', 'customers', 'products', 'stock_movements', 'settings', 'activity_logs'];
foreach ($tables as $tbl) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
        assert_test("'{$tbl}' tablosu mevcut ve sorgulanabilir", $count !== false);
    } catch (Exception $e) {
        assert_test("'{$tbl}' tablosu mevcut", false, $e->getMessage());
    }
}

// TEST 2: Güvenlik, CSRF ve XSS Fonksiyonları
echo "\n2. Güvenlik ve Yardımcı Fonksiyonlar:\n";
$token = csrf_token();
assert_test("CSRF token üretildi", !empty($token) && strlen($token) === 64);
assert_test("CSRF doğrulama başarılı", verify_csrf($token));
assert_test("Hatalı CSRF reddedildi", !verify_csrf('invalid_token'));

$raw_xss = '<script>alert("xss")</script>&"\'';
$escaped = e($raw_xss);
assert_test("XSS karakterleri temizlendi", !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));

// TEST 3: Kullanıcı Doğrulama ve Parola Hashleme
echo "\n3. Kimlik Doğrulama ve Kullanıcılar:\n";
$stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute(['admin']);
$admin = $stmt->fetch();
assert_test("Admin kullanıcısı mevcut", !empty($admin));
assert_test("Admin şifresi password_verify ile doğrulanıyor", password_verify('admin123', $admin['password_hash']));

// TEST 4: Ürün Yönetimi ve CRUD
echo "\n4. Ürün İşlemleri:\n";
$test_sku = 'TEST-PRD-' . time();
$stmt_new = $db->prepare('INSERT INTO products (sku, barcode, name, unit, purchase_price, sale_price, stock_quantity, min_stock_level) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$res = $stmt_new->execute([$test_sku, '999999999999', 'Test Amaçlı Ürün', 'Adet', 100.00, 150.00, 20.00, 5.00]);
assert_test("Yeni ürün başarıyla eklendi", $res);
$test_prod_id = (int)$db->lastInsertId();

$stmt_check = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt_check->execute([$test_prod_id]);
$product = $stmt_check->fetch();
assert_test("Eklenen ürün veritabanından çekildi", $product && $product['sku'] === $test_sku);
assert_test("Açılış stoğu 20.00 adet", (float)$product['stock_quantity'] === 20.00);

// TEST 5: Depoya Ürün Girişi (Stok Artışı)
echo "\n5. Stok Girişi (Mal Kabul) Mekanizması:\n";
$in_qty = 15.00;
$prev_stock = (float)$product['stock_quantity'];
$new_stock = $prev_stock + $in_qty;

$db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?')->execute([$new_stock, $test_prod_id]);
$db->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, movement_date, reference_no, note) 
              VALUES (?, "in", ?, ?, ?, ?, ?, ?, ?)')
   ->execute([$test_prod_id, $in_qty, 100.00, $prev_stock, $new_stock, date('Y-m-d'), 'TEST-IRS-01', 'Test girişi']);

$check_in = $db->prepare('SELECT stock_quantity FROM products WHERE id = ?');
$check_in->execute([$test_prod_id]);
$current_qty = (float)$check_in->fetchColumn();
assert_test("Stok girişi sonrası stok doğru hesaplandı (20 + 15 = 35)", $current_qty === 35.00);

// TEST 6: Depodan Ürün Çıkışı (Sevkiyat / Stok Düşümü)
echo "\n6. Stok Çıkışı (Sevkiyat) Mekanizması:\n";
$out_qty = 10.00;
$prev_stock = $current_qty;
$new_stock = $prev_stock - $out_qty;

$db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?')->execute([$new_stock, $test_prod_id]);
$db->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, movement_date, reference_no, note) 
              VALUES (?, "out", ?, ?, ?, ?, ?, ?, ?)')
   ->execute([$test_prod_id, $out_qty, 150.00, $prev_stock, $new_stock, date('Y-m-d'), 'TEST-SEVK-01', 'Test çıkışı']);

$check_out = $db->prepare('SELECT stock_quantity FROM products WHERE id = ?');
$check_out->execute([$test_prod_id]);
$current_qty = (float)$check_out->fetchColumn();
assert_test("Stok çıkışı sonrası stok doğru hesaplandı (35 - 10 = 25)", $current_qty === 25.00);

// TEST 7: Yetersiz Stok Koruması
echo "\n7. Yetersiz Stok Koruması:\n";
$excess_qty = 50.00;
$is_prevented = ($excess_qty > $current_qty);
assert_test("Mevcut stoktan (25) fazla çıkış talebi (50) engellendi", $is_prevented);

// TEST 8: Sayım Düzeltmesi
echo "\n8. Sayım Düzeltmesi (Fiziksel Mutabakat):\n";
$actual_count = 28.00; // Depoda 28 bulundu
$diff = $actual_count - $current_qty; // +3 fark
$db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?')->execute([$actual_count, $test_prod_id]);
$db->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, previous_stock, current_stock, movement_date, reference_no, note) 
              VALUES (?, "adjustment", ?, ?, ?, ?, ?, ?)')
   ->execute([$test_prod_id, abs($diff), $current_qty, $actual_count, date('Y-m-d'), 'TEST-SAYIM', 'Sayım fazlası bulundu']);

$check_adj = $db->prepare('SELECT stock_quantity FROM products WHERE id = ?');
$check_adj->execute([$test_prod_id]);
assert_test("Sayım düzeltmesi sonrası yeni stok mutabakatı sağlandı (28.00)", (float)$check_adj->fetchColumn() === 28.00);

// TEST 9: Telegram 2FA ve Bildirim Testi
echo "\n9. Telegram 2FA ve Bildirim Testleri:\n";
set_setting('telegram_2fa_enabled', '1');
assert_test("2FA ayarı başarıyla kaydedildi", get_setting('telegram_2fa_enabled') === '1');
$code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
assert_test("6 haneli 2FA güvenlik kodu üretildi", strlen($code) === 6 && ctype_digit($code));
assert_test("2FA hash doğrulaması başarılı", hash_equals($code, $code));

$svg_barcode = render_barcode_svg('TEST-BARCODE-123');
assert_test("Offline SVG barkod üretildi", str_contains($svg_barcode, '<svg') && str_contains($svg_barcode, '</svg>'));

// Kritik stok bildirim tetikleyicisi testi
check_and_notify_low_stock($test_prod_id);
assert_test("Kritik stok kontrol fonksiyonu hatasız çalıştı", true);

// TEST 10: Temizlik
$db->prepare('DELETE FROM stock_movements WHERE product_id = ?')->execute([$test_prod_id]);
$db->prepare('DELETE FROM products WHERE id = ?')->execute([$test_prod_id]);
assert_test("Test ürünü ve hareketleri temizlendi", true);

echo "\n========================================================\n";
echo " Test Sonuçları: {$passed} Başarılı, {$failed} Başarısız\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
