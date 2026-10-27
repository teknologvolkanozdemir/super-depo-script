<?php
/**
 * Süper Depo Scripti - Yeni Ürün Ekleme
 */

$page_title = 'Yeni Ürün Ekle';
$active_page = 'products';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

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
        $initial_stock = (float)str_replace(',', '.', $_POST['initial_stock'] ?? '0');
        $min_stock_level = (float)str_replace(',', '.', $_POST['min_stock_level'] ?? '5');
        $description = trim($_POST['description'] ?? '');

        if (empty($sku)) {
            $sku = generate_sku();
        }

        if (empty($name)) {
            $errors[] = 'Ürün adı alanı zorunludur.';
        }

        // SKU Benzersizlik Kontrolü
        $stmt_check = $db->prepare('SELECT COUNT(*) FROM products WHERE sku = ?');
        $stmt_check->execute([$sku]);
        if ($stmt_check->fetchColumn() > 0) {
            $errors[] = "Bu SKU ({$sku}) koduna sahip bir ürün zaten kayıtlı.";
        }

        // Barkod Benzersizlik Kontrolü (varsa)
        if ($barcode) {
            $stmt_bcheck = $db->prepare('SELECT COUNT(*) FROM products WHERE barcode = ?');
            $stmt_bcheck->execute([$barcode]);
            if ($stmt_bcheck->fetchColumn() > 0) {
                $errors[] = "Bu barkoda ({$barcode}) sahip bir ürün zaten kayıtlı.";
            }
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                $stmt_insert = $db->prepare('INSERT INTO products 
                    (sku, barcode, name, category_id, location_id, unit, purchase_price, sale_price, stock_quantity, min_stock_level, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt_insert->execute([
                    $sku, $barcode, $name, $category_id, $location_id, $unit,
                    $purchase_price, $sale_price, $initial_stock, $min_stock_level, $description
                ]);
                $product_id = (int)$db->lastInsertId();

                // Eğer açılış stoğu girilmişse ilk stok hareketi olarak kaydet
                if ($initial_stock > 0) {
                    $stmt_mov = $db->prepare('INSERT INTO stock_movements 
                        (product_id, movement_type, quantity, unit_price, previous_stock, current_stock, reference_no, movement_date, note, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt_mov->execute([
                        $product_id, 'in', $initial_stock, $purchase_price, 0.00, $initial_stock,
                        'ACILIS-STOK', date('Y-m-d'), 'Ürün kartı açılış stok kaydı', $user['id']
                    ]);
                }

                $db->commit();
                log_activity('product_create', "Yeni ürün eklendi: {$name} ({$sku})");
                set_flash('success', "'{$name}' ürünü başarıyla eklendi.");
                header("Location: product-view.php?id={$product_id}");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Ürün kaydedilirken bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

$default_sku = generate_sku();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Yeni Ürün Ekle</h1>
        <p class="page-subtitle">Depo envanterine yeni ürün kartı tanımlayın</p>
    </div>
    <div class="page-actions">
        <a href="products.php" class="btn btn-outline">&laquo; Ürün Listesine Dön</a>
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
    <form action="product-add.php" method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="sku" class="form-label">
                    Ürün Kodu (SKU) <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?= e($_POST['sku'] ?? $default_sku) ?>" required aria-required="true">
                <div class="form-hint">Benzersiz ürün kodu. Otomatik oluşturulabilir veya barkod okutulabilir.</div>
            </div>

            <div class="form-group">
                <label for="barcode" class="form-label">Barkod (EAN-13, QR vb.):</label>
                <input type="text" id="barcode" name="barcode" class="form-control" 
                       value="<?= e($_POST['barcode'] ?? '') ?>" placeholder="Örn: 8690123456789">
                <div class="form-hint">Barkod okuyucunuzla doğrudan bu alana taratabilirsiniz.</div>
            </div>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">
                Ürün Adı <span class="form-required" aria-hidden="true">*</span>
            </label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?= e($_POST['name'] ?? '') ?>" required aria-required="true" 
                   placeholder="Örn: Kablosuz Optik Fare">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id" class="form-label">Kategori:</label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value="">-- Kategori Seçiniz --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="location_id" class="form-label">Depo Raf / Konumu:</label>
                <select id="location_id" name="location_id" class="form-control">
                    <option value="">-- Depo / Raf Konumu Seçiniz --</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>" <?= ((int)($_POST['location_id'] ?? 0) === (int)$loc['id']) ? 'selected' : '' ?>>
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
                    $selected_unit = $_POST['unit'] ?? 'Adet';
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
                       value="<?= e($_POST['purchase_price'] ?? '0.00') ?>">
            </div>

            <div class="form-group">
                <label for="sale_price" class="form-label">Satış Fiyatı (₺):</label>
                <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-control" 
                       value="<?= e($_POST['sale_price'] ?? '0.00') ?>">
            </div>

            <div class="form-group">
                <label for="initial_stock" class="form-label">Açılış Stok Miktarı:</label>
                <input type="number" step="0.01" min="0" id="initial_stock" name="initial_stock" class="form-control" 
                       value="<?= e($_POST['initial_stock'] ?? '0') ?>">
                <div class="form-hint">Şu an depoda mevcut bulunan ilk sayım miktarı.</div>
            </div>

            <div class="form-group">
                <label for="min_stock_level" class="form-label">Kritik Stok Uyarısı Eşiği:</label>
                <input type="number" step="0.01" min="0" id="min_stock_level" name="min_stock_level" class="form-control" 
                       value="<?= e($_POST['min_stock_level'] ?? '5') ?>">
                <div class="form-hint">Bu miktarın altına düştüğünde sistem uyarı verir.</div>
            </div>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Ürün Açıklaması & Notlar:</label>
            <textarea id="description" name="description" class="form-control" rows="3" 
                      placeholder="Ürün özellikleri, tedarik detayları..."><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary btn-lg">
                <span aria-hidden="true">💾</span> Ürünü Kaydet
            </button>
            <a href="products.php" class="btn btn-outline btn-lg">İptal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
