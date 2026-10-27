<?php
/**
 * Süper Depo Scripti - Ürün Düzenleme
 */

$page_title = 'Ürün Düzenle';
$active_page = 'products';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Düzenlenecek ürün bulunamadı.');
    header('Location: products.php');
    exit;
}

$categories = $db->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$locations = $db->query('SELECT id, name FROM locations ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) geçersiz.';
    } else {
        $sku = trim($_POST['sku'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '') ?: null;
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
        $location_id = (int)($_POST['location_id'] ?? 0) ?: null;
        $unit = trim($_POST['unit'] ?? 'Adet') ?: 'Adet';
        $purchase_price = (float)str_replace(',', '.', $_POST['purchase_price'] ?? '0');
        $sale_price = (float)str_replace(',', '.', $_POST['sale_price'] ?? '0');
        $min_stock_level = (float)str_replace(',', '.', $_POST['min_stock_level'] ?? '5');
        $description = trim($_POST['description'] ?? '');

        if (empty($sku)) {
            $errors[] = 'Ürün kodu (SKU) boş bırakılamaz.';
        }

        if (empty($name)) {
            $errors[] = 'Ürün adı alanı zorunludur.';
        }

        // SKU Benzersizlik Kontrolü (kendisi hariç)
        $stmt_check = $db->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?');
        $stmt_check->execute([$sku, $id]);
        if ($stmt_check->fetchColumn() > 0) {
            $errors[] = "Bu SKU ({$sku}) koduna sahip başka bir ürün zaten kayıtlı.";
        }

        // Barkod Benzersizlik Kontrolü (kendisi hariç)
        if ($barcode) {
            $stmt_bcheck = $db->prepare('SELECT COUNT(*) FROM products WHERE barcode = ? AND id != ?');
            $stmt_bcheck->execute([$barcode, $id]);
            if ($stmt_bcheck->fetchColumn() > 0) {
                $errors[] = "Bu barkoda ({$barcode}) sahip başka bir ürün zaten kayıtlı.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt_update = $db->prepare('UPDATE products SET 
                    sku = ?, barcode = ?, name = ?, category_id = ?, location_id = ?, 
                    unit = ?, purchase_price = ?, sale_price = ?, min_stock_level = ?, description = ? 
                    WHERE id = ?');
                $stmt_update->execute([
                    $sku, $barcode, $name, $category_id, $location_id,
                    $unit, $purchase_price, $sale_price, $min_stock_level, $description, $id
                ]);

                log_activity('product_edit', "Ürün güncellendi: {$name} (ID: {$id})");
                set_flash('success', "'{$name}' ürünü başarıyla güncellendi.");
                header("Location: product-view.php?id={$id}");
                exit;
            } catch (Exception $e) {
                $errors[] = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Ürünü Düzenle: <?= e($product['name']) ?></h1>
        <p class="page-subtitle">SKU: <?= e($product['sku']) ?> | Mevcut Stok: <?= format_number($product['stock_quantity']) ?> <?= e($product['unit']) ?></p>
    </div>
    <div class="page-actions">
        <a href="product-view.php?id=<?= $id ?>" class="btn btn-outline">&laquo; Detaylara Dön</a>
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
    <form action="product-edit.php?id=<?= $id ?>" method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="sku" class="form-label">
                    Ürün Kodu (SKU) <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?= e($_POST['sku'] ?? $product['sku']) ?>" required aria-required="true">
            </div>

            <div class="form-group">
                <label for="barcode" class="form-label">Barkod:</label>
                <input type="text" id="barcode" name="barcode" class="form-control" 
                       value="<?= e($_POST['barcode'] ?? $product['barcode']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">
                Ürün Adı <span class="form-required" aria-hidden="true">*</span>
            </label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?= e($_POST['name'] ?? $product['name']) ?>" required aria-required="true">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id" class="form-label">Kategori:</label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value="">-- Kategori Seçiniz --</option>
                    <?php 
                    $cur_cat = (int)($_POST['category_id'] ?? $product['category_id']);
                    foreach ($categories as $cat): 
                    ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $cur_cat === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="location_id" class="form-label">Depo Raf / Konumu:</label>
                <select id="location_id" name="location_id" class="form-control">
                    <option value="">-- Depo / Raf Konumu Seçiniz --</option>
                    <?php 
                    $cur_loc = (int)($_POST['location_id'] ?? $product['location_id']);
                    foreach ($locations as $loc): 
                    ?>
                        <option value="<?= (int)$loc['id'] ?>" <?= $cur_loc === (int)$loc['id'] ? 'selected' : '' ?>>
                            <?= e($loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="unit" class="form-label">Ölçü Birimi:</label>
                <select id="unit" name="unit" class="form-control">
                    <?php 
                    $units = ['Adet', 'Kg', 'Koli', 'Paket', 'Kutu', 'Litre', 'Metre', 'Rulo', 'Set'];
                    $selected_unit = $_POST['unit'] ?? $product['unit'];
                    foreach ($units as $u): 
                    ?>
                        <option value="<?= e($u) ?>" <?= $selected_unit === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="purchase_price" class="form-label">Alış Fiyatı (₺):</label>
                <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" class="form-control" 
                       value="<?= e($_POST['purchase_price'] ?? $product['purchase_price']) ?>">
            </div>

            <div class="form-group">
                <label for="sale_price" class="form-label">Satış Fiyatı (₺):</label>
                <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-control" 
                       value="<?= e($_POST['sale_price'] ?? $product['sale_price']) ?>">
            </div>

            <div class="form-group">
                <label for="min_stock_level" class="form-label">Kritik Stok Uyarısı Eşiği:</label>
                <input type="number" step="0.01" min="0" id="min_stock_level" name="min_stock_level" class="form-control" 
                       value="<?= e($_POST['min_stock_level'] ?? $product['min_stock_level']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Ürün Açıklaması & Notlar:</label>
            <textarea id="description" name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary btn-lg">
                <span aria-hidden="true">💾</span> Değişiklikleri Kaydet
            </button>
            <a href="product-view.php?id=<?= $id ?>" class="btn btn-outline btn-lg">İptal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
