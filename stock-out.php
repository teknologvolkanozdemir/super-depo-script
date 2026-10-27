<?php
/**
 * Süper Depo Scripti - Depodan Ürün Çıkışı (Sevkiyat / Satış / Fire)
 */

$page_title = 'Ürün Çıkışı (Sevkiyat)';
$active_page = 'stock-out';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

$preselect_id = (int)($_GET['product_id'] ?? 0);

// Ürün Listesi
$products = $db->query('SELECT id, sku, barcode, name, unit, sale_price, stock_quantity 
                        FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

// Müşteriler
$customers = $db->query('SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) geçersiz.';
    } else {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $customer_id = (int)($_POST['customer_id'] ?? 0) ?: null;
        $quantity = (float)str_replace(',', '.', $_POST['quantity'] ?? '0');
        $unit_price = (float)str_replace(',', '.', $_POST['unit_price'] ?? '0');
        $reference_no = trim($_POST['reference_no'] ?? '');
        $movement_date = trim($_POST['movement_date'] ?? date('Y-m-d'));
        $out_reason = trim($_POST['out_reason'] ?? 'Satış / Sevkiyat');
        $note = trim($_POST['note'] ?? '');

        if ($product_id <= 0) {
            $errors[] = 'Lütfen depodan çıkışı yapılacak bir ürün seçiniz.';
        }

        if ($quantity <= 0) {
            $errors[] = 'Çıkış miktarı sıfırdan büyük olmalıdır.';
        }

        if (empty($movement_date)) {
            $movement_date = date('Y-m-d');
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                // Ürünün mevcut stoğunu kontrol et
                $stmt_prod = $db->prepare('SELECT id, name, sku, unit, stock_quantity, sale_price FROM products WHERE id = ?');
                $stmt_prod->execute([$product_id]);
                $prod = $stmt_prod->fetch();

                if (!$prod) {
                    throw new Exception('Seçilen ürün sistemde bulunamadı.');
                }

                $previous_stock = (float)$prod['stock_quantity'];

                // Yetersiz stok kontrolü
                if ($quantity > $previous_stock) {
                    throw new Exception("Yetersiz stok! Depoda yalnızca " . format_number($previous_stock) . " " . $prod['unit'] . " mevcut. Çıkış miktarı: " . format_number($quantity));
                }

                $new_stock = $previous_stock - $quantity;

                // Ürün stoğunu düşür
                $stmt_up = $db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?');
                $stmt_up->execute([$new_stock, $product_id]);

                // Stok hareketi kaydı
                $full_note = "Neden: {$out_reason}" . ($note ? " | Not: {$note}" : "");
                $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, customer_id, movement_date, note, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt_mov->execute([
                    $product_id, 'out', $quantity, $unit_price, $previous_stock, $new_stock,
                    $reference_no, $customer_id, $movement_date, $full_note, $user['id']
                ]);

                $db->commit();
                check_and_notify_low_stock($product_id);
                log_activity('stock_out', "Stok Çıkışı: {$prod['name']} (-{$quantity} {$prod['unit']}). Belge: {$reference_no}");
                set_flash('success', "'{$prod['name']}' ürününden {$quantity} {$prod['unit']} çıkış başarıyla tamamlandı. Kalan Stok: {$new_stock} {$prod['unit']}.");
                header('Location: stock-movements.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depodan Ürün Çıkışı (Sevkiyat)</h1>
        <p class="page-subtitle">Satış, mağaza sevkiyatı, transfer veya fire çıkış işlemleri</p>
    </div>
    <div class="page-actions">
        <a href="stock-movements.php" class="btn btn-outline">&laquo; Hareket Geçmişi</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert" aria-live="assertive">
    <span aria-hidden="true">⚠</span>
    <div>
        <strong>Hata Oluştu:</strong>
        <ul style="margin-left: 1.25rem; margin-top: 0.5rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form action="stock-out.php" method="POST" novalidate>
        <?= csrf_field() ?>

        <div style="background-color: var(--color-surface-hover); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--color-border);">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="barcode_search" class="form-label">
                    <span aria-hidden="true">🔍</span> Hızlı Barkod / SKU Okutucu (Opsiyonel):
                </label>
                <input type="text" id="barcode_search" class="form-control" 
                       placeholder="Barkod okuyucuyu buraya okutun ve Enter'a basın..." autofocus>
                <div class="form-hint">Barkodu okuttuğunuzda ilgili ürün listeden seçilecek ve mevcut stoğu kontrol edilecektir.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="product_id" class="form-label">
                    Çıkışı Yapılacak Ürün <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select id="product_id" name="product_id" class="form-control" required aria-required="true">
                    <option value="">-- Ürün Seçiniz --</option>
                    <?php 
                    $cur_prod = (int)($_POST['product_id'] ?? $preselect_id);
                    foreach ($products as $p): 
                    ?>
                        <option value="<?= (int)$p['id'] ?>" 
                                data-barcode="<?= e($p['barcode'] ?: '') ?>" 
                                data-sku="<?= e($p['sku']) ?>" 
                                data-stock="<?= format_number($p['stock_quantity']) ?>" 
                                data-unit="<?= e($p['unit']) ?>" 
                                data-price="<?= e($p['sale_price']) ?>"
                                <?= $cur_prod === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?>) - Stok: <?= format_number($p['stock_quantity']) ?> <?= e($p['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Mevcut Çıkılabilir Stok:</label>
                <div id="current_stock_display" style="padding: 0.625rem; background: var(--color-surface-hover); border-radius: var(--radius-md); font-weight: bold; border: 1px solid var(--color-border); color: var(--color-primary);">
                    -
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity" class="form-label">
                    Çıkış Miktarı <span class="form-required" aria-hidden="true">*</span>
                </label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="number" step="0.01" min="0.01" id="quantity" name="quantity" class="form-control" 
                           value="<?= e($_POST['quantity'] ?? '1') ?>" required aria-required="true">
                    <span id="unit_display" style="font-weight: 600; min-width: 45px;">Birim</span>
                </div>
            </div>

            <div class="form-group">
                <label for="unit_price" class="form-label">Birim Satış Fiyatı (₺):</label>
                <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" class="form-control" 
                       value="<?= e($_POST['unit_price'] ?? '') ?>" placeholder="0.00">
            </div>

            <div class="form-group">
                <label class="form-label">Toplam Çıkış Değeri:</label>
                <div id="total_amount_display" style="padding: 0.625rem; background: var(--color-surface-hover); border-radius: var(--radius-md); font-weight: bold; border: 1px solid var(--color-border); color: var(--color-danger);">
                    0,00 ₺
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="out_reason" class="form-label">Çıkış Nedeni:</label>
                <select id="out_reason" name="out_reason" class="form-control">
                    <option value="Satış / Müşteri Sevkiyatı">Satış / Müşteri Sevkiyatı</option>
                    <option value="Şube / Mağaza Transferi">Şube / Mağaza Transferi</option>
                    <option value="Fire / Hasar / Kırık">Fire / Hasar / Kırık</option>
                    <option value="Tedarikçiye İade">Tedarikçiye İade</option>
                    <option value="Numune / Tanıtım">Numune / Tanıtım</option>
                    <option value="Diğer Çıkış">Diğer Çıkış</option>
                </select>
            </div>

            <div class="form-group">
                <label for="customer_id" class="form-label">Alıcı / Müşteri:</label>
                <select id="customer_id" name="customer_id" class="form-control">
                    <option value="">-- Müşteri Seçiniz (Opsiyonel) --</option>
                    <?php 
                    $cur_cust = (int)($_POST['customer_id'] ?? 0);
                    foreach ($customers as $c): 
                    ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $cur_cust === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="reference_no" class="form-label">Sevk İrsaliyesi / Sipariş No:</label>
                <input type="text" id="reference_no" name="reference_no" class="form-control" 
                       value="<?= e($_POST['reference_no'] ?? '') ?>" placeholder="Örn: SEVK-2026-104">
            </div>

            <div class="form-group">
                <label for="movement_date" class="form-label">Çıkış Tarihi:</label>
                <input type="date" id="movement_date" name="movement_date" class="form-control" 
                       value="<?= e($_POST['movement_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="note" class="form-label">Sevkiyat Notu & Açıklama:</label>
            <textarea id="note" name="note" class="form-control" rows="2" 
                      placeholder="Kargo takip no, teslim alan kişi veya açıklama..."><?= e($_POST['note'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-danger btn-lg">
                <span aria-hidden="true">📤</span> Stok Çıkışını Onayla & Sevk Et
            </button>
            <a href="products.php" class="btn btn-outline btn-lg">İptal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
