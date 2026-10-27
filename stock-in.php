<?php
/**
 * Süper Depo Scripti - Depoya Ürün Girişi (Mal Kabul / Alış)
 */

$page_title = 'Ürün Girişi (Mal Kabul)';
$active_page = 'stock-in';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

$preselect_id = (int)($_GET['product_id'] ?? 0);

// Ürün Listesi
$products = $db->query('SELECT id, sku, barcode, name, unit, purchase_price, stock_quantity 
                        FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

// Tedarikçiler
$suppliers = $db->query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) geçersiz.';
    } else {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $quantity = (float)str_replace(',', '.', $_POST['quantity'] ?? '0');
        $unit_price = (float)str_replace(',', '.', $_POST['unit_price'] ?? '0');
        $reference_no = trim($_POST['reference_no'] ?? '');
        $movement_date = trim($_POST['movement_date'] ?? date('Y-m-d'));
        $note = trim($_POST['note'] ?? '');
        $update_cost = isset($_POST['update_cost']) && $_POST['update_cost'] === '1';

        if ($product_id <= 0) {
            $errors[] = 'Lütfen depoya girişi yapılacak bir ürün seçiniz.';
        }

        if ($quantity <= 0) {
            $errors[] = 'Giriş miktarı sıfırdan büyük bir sayı olmalıdır.';
        }

        if (empty($movement_date)) {
            $movement_date = date('Y-m-d');
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                // Ürünün mevcut stoğunu al ve kilitle (lock/check)
                $stmt_prod = $db->prepare('SELECT id, name, sku, unit, stock_quantity, purchase_price FROM products WHERE id = ? FOR UPDATE');
                try {
                    $stmt_prod->execute([$product_id]);
                } catch (Exception $e) {
                    // SQLite FOR UPDATE desteklemezse fallback
                    $stmt_prod = $db->prepare('SELECT id, name, sku, unit, stock_quantity, purchase_price FROM products WHERE id = ?');
                    $stmt_prod->execute([$product_id]);
                }
                $prod = $stmt_prod->fetch();

                if (!$prod) {
                    throw new Exception('Seçilen ürün sistemde bulunamadı.');
                }

                $previous_stock = (float)$prod['stock_quantity'];
                $new_stock = $previous_stock + $quantity;

                // Ürün stoğunu güncelle
                if ($update_cost && $unit_price > 0) {
                    $stmt_up = $db->prepare('UPDATE products SET stock_quantity = ?, purchase_price = ? WHERE id = ?');
                    $stmt_up->execute([$new_stock, $unit_price, $product_id]);
                } else {
                    $stmt_up = $db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?');
                    $stmt_up->execute([$new_stock, $product_id]);
                }

                // Stok hareketi kaydı
                $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, supplier_id, movement_date, note, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt_mov->execute([
                    $product_id, 'in', $quantity, $unit_price, $previous_stock, $new_stock,
                    $reference_no, $supplier_id, $movement_date, $note, $user['id']
                ]);

                $db->commit();
                log_activity('stock_in', "Stok Girişi: {$prod['name']} (+{$quantity} {$prod['unit']}). Belge: {$reference_no}");
                set_flash('success', "'{$prod['name']}' ürününe {$quantity} {$prod['unit']} giriş başarıyla kaydedildi. Güncel Stok: {$new_stock} {$prod['unit']}.");
                header('Location: stock-movements.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Stok girişi kaydedilemedi: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depoya Ürün Girişi (Mal Kabul)</h1>
        <p class="page-subtitle">Satın alınan veya transfer ile depoya giren ürünlerin kaydı</p>
    </div>
    <div class="page-actions">
        <a href="stock-movements.php" class="btn btn-outline">&laquo; Hareket Geçmişi</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert" aria-live="assertive">
    <span aria-hidden="true">⚠</span>
    <div>
        <strong>Lütfen aşağıdaki hataları düzeltin:</strong>
        <ul style="margin-left: 1.25rem; margin-top: 0.5rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form action="stock-in.php" method="POST" novalidate>
        <?= csrf_field() ?>

        <div style="background-color: var(--color-surface-hover); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--color-border);">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="barcode_search" class="form-label">
                    <span aria-hidden="true">🔍</span> Hızlı Barkod / SKU Okutucu (Opsiyonel):
                </label>
                <input type="text" id="barcode_search" class="form-control" 
                       placeholder="Barkod okuyucuyu buraya okutun ve Enter'a basın..." autofocus>
                <div class="form-hint">Barkodu okuttuğunuzda ilgili ürün aşağıdaki listeden otomatik seçilecektir.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="product_id" class="form-label">
                    Giriş Yapılacak Ürün <span class="form-required" aria-hidden="true">*</span>
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
                                data-price="<?= e($p['purchase_price']) ?>"
                                <?= $cur_prod === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?>) - Mevcut: <?= format_number($p['stock_quantity']) ?> <?= e($p['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Mevcut Stok Bilgisi:</label>
                <div id="current_stock_display" style="padding: 0.625rem; background: var(--color-surface-hover); border-radius: var(--radius-md); font-weight: bold; border: 1px solid var(--color-border);">
                    -
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity" class="form-label">
                    Giriş Miktarı <span class="form-required" aria-hidden="true">*</span>
                </label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="number" step="0.01" min="0.01" id="quantity" name="quantity" class="form-control" 
                           value="<?= e($_POST['quantity'] ?? '1') ?>" required aria-required="true">
                    <span id="unit_display" style="font-weight: 600; min-width: 45px;">Birim</span>
                </div>
            </div>

            <div class="form-group">
                <label for="unit_price" class="form-label">Birim Alış Maliyeti (₺):</label>
                <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" class="form-control" 
                       value="<?= e($_POST['unit_price'] ?? '') ?>" placeholder="0.00">
            </div>

            <div class="form-group">
                <label class="form-label">Toplam Giriş Tutarı:</label>
                <div id="total_amount_display" style="padding: 0.625rem; background: var(--color-surface-hover); border-radius: var(--radius-md); font-weight: bold; border: 1px solid var(--color-border); color: var(--color-success);">
                    0,00 ₺
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="supplier_id" class="form-label">Tedarikçi Firma:</label>
                <select id="supplier_id" name="supplier_id" class="form-control">
                    <option value="">-- Tedarikçi Seçiniz (Opsiyonel) --</option>
                    <?php 
                    $cur_sup = (int)($_POST['supplier_id'] ?? 0);
                    foreach ($suppliers as $s): 
                    ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $cur_sup === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="reference_no" class="form-label">İrsaliye / Fatura / Fiş No:</label>
                <input type="text" id="reference_no" name="reference_no" class="form-control" 
                       value="<?= e($_POST['reference_no'] ?? '') ?>" placeholder="Örn: IRS-2026-981">
            </div>

            <div class="form-group">
                <label for="movement_date" class="form-label">İşlem / Giriş Tarihi:</label>
                <input type="date" id="movement_date" name="movement_date" class="form-control" 
                       value="<?= e($_POST['movement_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="note" class="form-label">Giriş Notu & Detaylar:</label>
            <textarea id="note" name="note" class="form-control" rows="2" 
                      placeholder="Mal kabul kontrolü, parti no veya özel notlar..."><?= e($_POST['note'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="update_cost" value="1" <?= isset($_POST['update_cost']) ? 'checked' : 'checked' ?>>
                <span>Ürünün ana kartındaki alış maliyeti fiyatını bu yeni birim fiyat ile güncelle</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-success btn-lg">
                <span aria-hidden="true">📥</span> Stok Girişini Onayla & Kaydet
            </button>
            <a href="products.php" class="btn btn-outline btn-lg">İptal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
