<?php
/**
 * Süper Depo Scripti - REST API ve WooCommerce Webhook Uç Noktası
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$action = $_GET['action'] ?? '';

// 1. Stok Sorgulama (SKU veya Barkod ile)
if ($action === 'get_stock') {
    $sku = trim($_GET['sku'] ?? '');
    $barcode = trim($_GET['barcode'] ?? '');

    if (empty($sku) && empty($barcode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Lütfen SKU veya Barkod parametresi belirtin.']);
        exit;
    }

    $stmt = $db->prepare('SELECT p.id, p.sku, p.barcode, p.name, p.stock_quantity, p.min_stock_level, 
                                 p.unit, p.sale_price, c.name as category_name, l.name as location_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          LEFT JOIN locations l ON p.location_id = l.id 
                          WHERE (p.sku = ? OR p.barcode = ?) AND p.is_active = 1 LIMIT 1');
    $stmt->execute([$sku, $barcode]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => [
                'id' => (int)$product['id'],
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'name' => $product['name'],
                'stock' => (float)$product['stock_quantity'],
                'min_stock' => (float)$product['min_stock_level'],
                'unit' => $product['unit'],
                'price' => (float)$product['sale_price'],
                'category' => $product['category_name'],
                'location' => $product['location_name']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ürün bulunamadı.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 2. WooCommerce Sipariş Webhook Dinleyicisi
if ($action === 'wc_webhook') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if (empty($data) || empty($data['line_items'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geçersiz sipariş verisi veya boş satır öğeleri.']);
        exit;
    }

    $order_id = $data['id'] ?? 'Bilinmeyen';
    $customer_name = ($data['billing']['first_name'] ?? '') . ' ' . ($data['billing']['last_name'] ?? '');
    $updated_count = 0;
    $errors = [];

    $db->beginTransaction();
    try {
        foreach ($data['line_items'] as $item) {
            $sku = trim($item['sku'] ?? '');
            $qty = (float)($item['quantity'] ?? 1);

            if (empty($sku)) continue;

            $stmt_prod = $db->prepare('SELECT id, name, unit, stock_quantity, sale_price FROM products WHERE sku = ? AND is_active = 1');
            $stmt_prod->execute([$sku]);
            $prod = $stmt_prod->fetch();

            if ($prod) {
                $prev_stock = (float)$prod['stock_quantity'];
                $new_stock = max(0, $prev_stock - $qty);

                // Stok düş
                $stmt_up = $db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?');
                $stmt_up->execute([$new_stock, $prod['id']]);

                // Hareket kaydı oluştur
                $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, movement_date, note) 
                    VALUES (?, "out", ?, ?, ?, ?, ?, ?, ?)');
                $stmt_mov->execute([
                    $prod['id'], $qty, $prod['sale_price'], $prev_stock, $new_stock,
                    "WC-SIPARIS-#{$order_id}", date('Y-m-d'), "WooCommerce Otomatik Sipariş Düşümü | Alıcı: {$customer_name}"
                ]);

                $updated_count++;
            } else {
                $errors[] = "SKU ({$sku}) depoda bulunamadı.";
            }
        }

        $db->commit();
        log_activity('wc_webhook', "WooCommerce Sipariş #{$order_id} işlendi. {$updated_count} ürünün stoğu düşüldü.");
        echo json_encode([
            'success' => true,
            'message' => "Sipariş #{$order_id} başarıyla işlendi.",
            'updated_items_count' => $updated_count,
            'warnings' => $errors
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. Genel Ürün Listesi API
if ($action === 'products') {
    $stmt = $db->query('SELECT id, sku, barcode, name, stock_quantity, unit, sale_price FROM products WHERE is_active = 1 ORDER BY name ASC');
    $products = $stmt->fetchAll();
    echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
    exit;
}

// Bilinmeyen eylem
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Geçersiz API eylemi. Desteklenen: get_stock, wc_webhook, products.']);
