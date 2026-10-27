<?php
/**
 * Süper Depo Scripti - Sayım Düzeltme ve Depo İçi Konum Transferi
 */

$page_title = 'Sayım Düzeltme & Konum Transferi';
$active_page = 'adjustment';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

$products = $db->query('SELECT p.*, l.name as location_name 
                        FROM products p 
                        LEFT JOIN locations l ON p.location_id = l.id 
                        WHERE p.is_active = 1 ORDER BY p.name ASC')->fetchAll();

$locations = $db->query('SELECT id, name, warehouse_name FROM locations ORDER BY warehouse_name, name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) geçersiz.';
    } else {
        $action_type = $_POST['action_type'] ?? 'adjustment'; // 'adjustment' veya 'transfer'
        $product_id = (int)($_POST['product_id'] ?? 0);

        if ($product_id <= 0) {
            $errors[] = 'Lütfen işlem yapılacak ürünü seçiniz.';
        } else {
            $stmt_prod = $db->prepare('SELECT * FROM products WHERE id = ?');
            $stmt_prod->execute([$product_id]);
            $prod = $stmt_prod->fetch();

            if (!$prod) {
                $errors[] = 'Ürün bulunamadı.';
            } else {
                $prev_stock = (float)$prod['stock_quantity'];

                if ($action_type === 'adjustment') {
                    // Sayım Düzeltme
                    $new_stock = (float)str_replace(',', '.', $_POST['actual_stock'] ?? '0');
                    if ($new_stock < 0) {
                        $errors[] = 'Gerçek sayım miktarı negatif olamaz.';
                    } else {
                        $diff = $new_stock - $prev_stock;
                        $note = trim($_POST['adj_note'] ?? 'Fiziksel sayım mutabakatı');
                        $ref_no = trim($_POST['ref_no'] ?? 'SAYIM-' . date('Ymd'));

                        $db->beginTransaction();
                        try {
                            $stmt_up = $db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?');
                            $stmt_up->execute([$new_stock, $product_id]);

                            $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                                (product_id, movement_type, quantity, previous_stock, current_stock, reference_no, movement_date, note, user_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt_mov->execute([
                                $product_id, 'adjustment', abs($diff), $prev_stock, $new_stock,
                                $ref_no, date('Y-m-d'), "Fark: " . ($diff >= 0 ? "+{$diff}" : "{$diff}") . " | " . $note, $user['id']
                            ]);

                            $db->commit();
                            check_and_notify_low_stock($product_id);
                            log_activity('stock_adjustment', "Sayım düzeltmesi: {$prod['name']} ({$prev_stock} -> {$new_stock})");
                            set_flash('success', "'{$prod['name']}' stoğu {$new_stock} {$prod['unit']} olarak düzeltildi.");
                            header('Location: stock-movements.php');
                            exit;
                        } catch (Exception $e) {
                            $db->rollBack();
                            $errors[] = 'Hata: ' . $e->getMessage();
                        }
                    }
                } elseif ($action_type === 'transfer') {
                    // Depo İçi Konum Transferi
                    $new_loc_id = (int)($_POST['new_location_id'] ?? 0);
                    $note = trim($_POST['trans_note'] ?? 'Depo içi raf transferi');

                    if ($new_loc_id <= 0) {
                        $errors[] = 'Lütfen yeni raf/depo konumunu seçiniz.';
                    } else {
                        $db->beginTransaction();
                        try {
                            $old_loc_id = $prod['location_id'];

                            $stmt_up = $db->prepare('UPDATE products SET location_id = ? WHERE id = ?');
                            $stmt_up->execute([$new_loc_id, $product_id]);

                            $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                                (product_id, movement_type, quantity, previous_stock, current_stock, from_location_id, to_location_id, movement_date, note, user_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt_mov->execute([
                                $product_id, 'transfer', $prev_stock, $prev_stock, $prev_stock,
                                $old_loc_id, $new_loc_id, date('Y-m-d'), $note, $user['id']
                            ]);

                            $db->commit();
                            log_activity('location_transfer', "Konum transferi: {$prod['name']}");
                            set_flash('success', "'{$prod['name']}' ürünü yeni depo/raf konumuna aktarıldı.");
                            header('Location: product-view.php?id=' . $product_id);
                            exit;
                        } catch (Exception $e) {
                            $db->rollBack();
                            $errors[] = 'Hata: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sayım Düzeltme & Konum Transferi</h1>
        <p class="page-subtitle">Depo envanter sayım mutabakatları ve raflar arası ürün transferleri</p>
    </div>
    <div class="page-actions">
        <a href="products.php" class="btn btn-outline">&laquo; Ürünler</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert" aria-live="assertive">
    <span aria-hidden="true">⚠</span>
    <div>
        <strong>İşlem Başarısız:</strong>
        <ul style="margin-left: 1.25rem; margin-top: 0.5rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem;">
    <!-- 1. Sayım Düzeltme Kartı -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">⚖️ Fiziksel Sayım Düzeltme</h2>
        </div>
        <form action="stock-adjustment.php" method="POST" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="adjustment">

            <div class="form-group">
                <label for="adj_product_id" class="form-label">
                    Düzeltilecek Ürün <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select id="adj_product_id" name="product_id" class="form-control" required>
                    <option value="">-- Ürün Seçiniz --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-stock="<?= format_number($p['stock_quantity']) ?>">
                            <?= e($p['name']) ?> (Sistemde: <?= format_number($p['stock_quantity']) ?> <?= e($p['unit']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="actual_stock" class="form-label">
                    Fiziksel Sayımda Çıkan Gerçek Miktar <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input type="number" step="0.01" min="0" id="actual_stock" name="actual_stock" class="form-control" required placeholder="0.00">
                <div class="form-hint">Depoda gözle sayılan net miktar. Sistem aradaki farkı otomatik hesaplayacaktır.</div>
            </div>

            <div class="form-group">
                <label for="ref_no" class="form-label">Sayım Fiş / Belge No:</label>
                <input type="text" id="ref_no" name="ref_no" class="form-control" value="SAYIM-<?= date('Ymd') ?>">
            </div>

            <div class="form-group">
                <label for="adj_note" class="form-label">Sayım Notu / Gerekçe:</label>
                <textarea id="adj_note" name="adj_note" class="form-control" rows="2" placeholder="Örn: Yıl sonu sayımı, eksik ürün mutabakatı..."></textarea>
            </div>

            <button type="submit" class="btn btn-warning" style="width: 100%;">
                <span aria-hidden="true">✓</span> Sayım Stoğunu Kaydet
            </button>
        </form>
    </div>

    <!-- 2. Raf ve Konum Transferi Kartı -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📍 Depo / Raf Konumu Değiştirme</h2>
        </div>
        <form action="stock-adjustment.php" method="POST" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="transfer">

            <div class="form-group">
                <label for="trans_product_id" class="form-label">
                    Transfer Edilecek Ürün <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select id="trans_product_id" name="product_id" class="form-control" required>
                    <option value="">-- Ürün Seçiniz --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= e($p['name']) ?> (Mevcut: <?= e($p['location_name'] ?: 'Tanımsız') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="new_location_id" class="form-label">
                    Hedef Yeni Raf / Depo Konumu <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select id="new_location_id" name="new_location_id" class="form-control" required>
                    <option value="">-- Yeni Konumu Seçiniz --</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>">
                            <?= e($loc['warehouse_name']) ?> - <?= e($loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="trans_note" class="form-label">Transfer Nedeni / Açıklama:</label>
                <textarea id="trans_note" name="trans_note" class="form-control" rows="4" placeholder="Örn: B Blok genişletme nedeniyle raf aktarımı..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <span aria-hidden="true">⇄</span> Konum Transferini Uygula
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
