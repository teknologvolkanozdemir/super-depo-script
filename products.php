<?php
/**
 * Süper Depo Scripti - Ürün Listesi ve Yönetimi
 */

$page_title = 'Ürün Yönetimi';
$active_page = 'products';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();

// Ürün Silme İşlemi (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    require_role(['admin', 'manager']);
    if (!verify_csrf()) {
        set_flash('danger', 'Güvenlik doğrulaması (CSRF) geçersiz.');
    } else {
        $delete_id = (int)($_POST['product_id'] ?? 0);
        $stmt_check = $db->prepare('SELECT name FROM products WHERE id = ?');
        $stmt_check->execute([$delete_id]);
        $prod_name = $stmt_check->fetchColumn();

        if ($prod_name) {
            $stmt_del = $db->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
            $stmt_del->execute([$delete_id]);
            log_activity('product_delete', "Ürün arşivlendi/silindi: {$prod_name} (ID: {$delete_id})");
            set_flash('success', "'{$prod_name}' ürünü başarıyla silindi (arşive alındı).");
        }
    }
    header('Location: products.php');
    exit;
}

// Filtreler ve Arama
$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$location_id = (int)($_GET['location_id'] ?? 0);
$stock_status = $_GET['stock_status'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where = ['p.is_active = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category_id > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $category_id;
}

if ($location_id > 0) {
    $where[] = 'p.location_id = ?';
    $params[] = $location_id;
}

if ($stock_status === 'out') {
    $where[] = 'p.stock_quantity <= 0';
} elseif ($stock_status === 'critical') {
    $where[] = 'p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level';
} elseif ($stock_status === 'normal') {
    $where[] = 'p.stock_quantity > p.min_stock_level';
}

$order_by = 'p.name ASC';
if ($sort === 'name_desc') $order_by = 'p.name DESC';
elseif ($sort === 'stock_asc') $order_by = 'p.stock_quantity ASC';
elseif ($sort === 'stock_desc') $order_by = 'p.stock_quantity DESC';
elseif ($sort === 'price_asc') $order_by = 'p.sale_price ASC';
elseif ($sort === 'price_desc') $order_by = 'p.sale_price DESC';
elseif ($sort === 'newest') $order_by = 'p.id DESC';

$where_clause = implode(' AND ', $where);

// Toplam Kayıt Sayısı
$count_stmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE {$where_clause}");
$count_stmt->execute($params);
$total_products = (int)$count_stmt->fetchColumn();

// Ürün Listesi
$query = "SELECT p.*, c.name as category_name, l.name as location_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN locations l ON p.location_id = l.id 
          WHERE {$where_clause} 
          ORDER BY {$order_by} 
          LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Filtre Seçenekleri
$categories = $db->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$locations = $db->query('SELECT id, name FROM locations ORDER BY name ASC')->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depo Ürünleri</h1>
        <p class="page-subtitle">Toplam <?= format_number($total_products, 0) ?> ürün listeleniyor</p>
    </div>
    <div class="page-actions">
        <a href="product-add.php" class="btn btn-primary" title="Yeni Ürün Kartı Ekle (Alt+N)">
            <span aria-hidden="true">➕</span> Yeni Ürün Ekle
        </a>
        <a href="reports.php?export=products_csv" class="btn btn-outline" title="Ürünleri CSV formatında indir">
            <span aria-hidden="true">📥</span> CSV Dışa Aktar
        </a>
    </div>
</div>

<!-- Arama ve Filtreleme Formu -->
<form method="GET" action="products.php" class="filter-bar" role="search" aria-label="Ürün Filtreleme">
    <div class="form-group" style="flex: 2;">
        <label for="search-input" class="form-label">Arama (Ad, SKU, Barkod):</label>
        <input type="search" id="search-input" name="search" class="form-control" 
               placeholder="Barkod okutun veya ürün adı yazın..." value="<?= e($search) ?>" autofocus>
    </div>

    <div class="form-group">
        <label for="category_id" class="form-label">Kategori:</label>
        <select id="category_id" name="category_id" class="form-control">
            <option value="">Tüm Kategoriler</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="location_id" class="form-label">Konum / Raf:</label>
        <select id="location_id" name="location_id" class="form-control">
            <option value="">Tüm Konumlar</option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= (int)$loc['id'] ?>" <?= $location_id === (int)$loc['id'] ? 'selected' : '' ?>>
                    <?= e($loc['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="stock_status" class="form-label">Stok Durumu:</label>
        <select id="stock_status" name="stock_status" class="form-control">
            <option value="">Tümü</option>
            <option value="critical" <?= $stock_status === 'critical' ? 'selected' : '' ?>>Kritik Stokta Olanlar</option>
            <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Tükenenler (0 Stok)</option>
            <option value="normal" <?= $stock_status === 'normal' ? 'selected' : '' ?>>Yeterli Stok</option>
        </select>
    </div>

    <div class="form-group">
        <label for="sort" class="form-label">Sıralama:</label>
        <select id="sort" name="sort" class="form-control">
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>İsim (A-Z)</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>İsim (Z-A)</option>
            <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Stok (Azalan)</option>
            <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stok (Artan)</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Fiyat (En Düşük)</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Fiyat (En Yüksek)</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>En Yeni Eklenenler</option>
        </select>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
        <button type="submit" class="btn btn-primary" title="Filtreleri Uygula">Filtrele</button>
        <a href="products.php" class="btn btn-outline" title="Filtreleri Temizle">Sıfırla</a>
    </div>
</form>

<!-- Ürünler Tablosu -->
<div class="table-responsive">
    <table class="data-table" aria-label="Depodaki Ürünler Tablosu">
        <caption>Kayıtlı Depo Ürünleri Listesi</caption>
        <thead>
            <tr>
                <th scope="col">SKU / Barkod</th>
                <th scope="col">Ürün Adı</th>
                <th scope="col">Kategori</th>
                <th scope="col">Depo Konumu</th>
                <th scope="col">Alış Fiyatı</th>
                <th scope="col">Satış Fiyatı</th>
                <th scope="col">Stok Miktarı</th>
                <th scope="col" style="text-align: right;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
                    Arama kriterlerine uygun ürün bulunamadı.
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <code><?= e($p['sku']) ?></code>
                        <?php if ($p['barcode']): ?>
                            <small class="text-muted" style="display: block;">Barkod: <?= e($p['barcode']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>
                            <a href="product-view.php?id=<?= (int)$p['id'] ?>" style="color: inherit; text-decoration: none;">
                                <?= e($p['name']) ?>
                            </a>
                        </strong>
                    </td>
                    <td><?= e($p['category_name'] ?: '-') ?></td>
                    <td><?= e($p['location_name'] ?: '-') ?></td>
                    <td><?= format_currency($p['purchase_price']) ?></td>
                    <td><strong><?= format_currency($p['sale_price']) ?></strong></td>
                    <td>
                        <?= get_stock_badge((float)$p['stock_quantity'], (float)$p['min_stock_level']) ?>
                        <small class="text-muted" style="margin-left: 4px;"><?= e($p['unit']) ?></small>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        <a href="product-view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline" title="Ürün Detayları ve Etiket">
                            Detay
                        </a>
                        <a href="stock-in.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-success" title="Depoya Giriş Yap">
                            + Giriş
                        </a>
                        <a href="stock-out.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-danger" title="Depodan Çıkış Yap">
                            - Çıkış
                        </a>
                        <a href="product-edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline" title="Ürünü Düzenle">
                            Düzenle
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Sayfalama -->
<?php
$query_params = $_GET;
unset($query_params['page']);
$base_pagination_url = 'products.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
echo render_pagination($total_products, $per_page, $page, $base_pagination_url);
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
