<?php
/**
 * Süper Depo Scripti - Stok Hareketleri Geçmişi
 */

$page_title = 'Stok Hareketleri';
$active_page = 'movements';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();

$product_id = (int)($_GET['product_id'] ?? 0);
$movement_type = trim($_GET['movement_type'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];

if ($product_id > 0) {
    $where[] = 'sm.product_id = ?';
    $params[] = $product_id;
}

if ($movement_type !== '') {
    $where[] = 'sm.movement_type = ?';
    $params[] = $movement_type;
}

if ($start_date !== '') {
    $where[] = 'sm.movement_date >= ?';
    $params[] = $start_date;
}

if ($end_date !== '') {
    $where[] = 'sm.movement_date <= ?';
    $params[] = $end_date;
}

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR sm.reference_no LIKE ? OR sm.note LIKE ?)';
    $st = "%{$search}%";
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
}

$where_clause = implode(' AND ', $where);

// Toplam Kayıt
$count_stmt = $db->prepare("SELECT COUNT(*) FROM stock_movements sm 
                            JOIN products p ON sm.product_id = p.id 
                            WHERE {$where_clause}");
$count_stmt->execute($params);
$total_movements = (int)$count_stmt->fetchColumn();

// Hareketler
$query = "SELECT sm.*, p.name as product_name, p.sku, p.unit, u.full_name as user_name, 
                 s.name as supplier_name, c.name as customer_name 
          FROM stock_movements sm 
          JOIN products p ON sm.product_id = p.id 
          LEFT JOIN users u ON sm.user_id = u.id 
          LEFT JOIN suppliers s ON sm.supplier_id = s.id 
          LEFT JOIN customers c ON sm.customer_id = c.id 
          WHERE {$where_clause} 
          ORDER BY sm.id DESC 
          LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll();

// Ürünler
$products = $db->query('SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Stok Hareket Geçmişi</h1>
        <p class="page-subtitle">Depoya giren, çıkan, transfer edilen ve düzeltilen tüm ürün hareketleri</p>
    </div>
    <div class="page-actions">
        <a href="stock-in.php" class="btn btn-success">+ Ürün Girişi</a>
        <a href="stock-out.php" class="btn btn-danger">- Ürün Çıkışı</a>
        <a href="stock-adjustment.php" class="btn btn-outline">⚖ Sayım & Transfer</a>
        <a href="reports.php?export=movements_csv<?= !empty($_GET) ? '&' . http_build_query($_GET) : '' ?>" class="btn btn-outline" title="CSV olarak indir">
            📥 CSV İndir
        </a>
    </div>
</div>

<!-- Filtreleme Alanı -->
<form method="GET" action="stock-movements.php" class="filter-bar" role="search" aria-label="Hareket Filtreleri">
    <div class="form-group">
        <label for="search" class="form-label">Arama (Ürün, Belge No, Not):</label>
        <input type="text" id="search" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Örn: IRS-2026...">
    </div>

    <div class="form-group">
        <label for="product_id" class="form-label">Ürün:</label>
        <select id="product_id" name="product_id" class="form-control">
            <option value="">Tüm Ürünler</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $product_id === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="movement_type" class="form-label">İşlem Türü:</label>
        <select id="movement_type" name="movement_type" class="form-control">
            <option value="">Tümü</option>
            <option value="in" <?= $movement_type === 'in' ? 'selected' : '' ?>>Giriş (Mal Kabul)</option>
            <option value="out" <?= $movement_type === 'out' ? 'selected' : '' ?>>Çıkış (Sevkiyat)</option>
            <option value="transfer" <?= $movement_type === 'transfer' ? 'selected' : '' ?>>Transfer</option>
            <option value="adjustment" <?= $movement_type === 'adjustment' ? 'selected' : '' ?>>Sayım / Düzeltme</option>
        </select>
    </div>

    <div class="form-group">
        <label for="start_date" class="form-label">Başlangıç Tarihi:</label>
        <input type="date" id="start_date" name="start_date" class="form-control" value="<?= e($start_date) ?>">
    </div>

    <div class="form-group">
        <label for="end_date" class="form-label">Bitiş Tarihi:</label>
        <input type="date" id="end_date" name="end_date" class="form-control" value="<?= e($end_date) ?>">
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
        <button type="submit" class="btn btn-primary">Filtrele</button>
        <a href="stock-movements.php" class="btn btn-outline">Temizle</a>
    </div>
</form>

<!-- Tablo -->
<div class="table-responsive">
    <table class="data-table" aria-label="Stok Hareketleri Listesi">
        <caption>Kayıtlı Stok Hareketleri (Toplam <?= format_number($total_movements, 0) ?> İşlem)</caption>
        <thead>
            <tr>
                <th scope="col">İşlem No</th>
                <th scope="col">Tarih</th>
                <th scope="col">Tür</th>
                <th scope="col">Ürün Adı & SKU</th>
                <th scope="col">Miktar</th>
                <th scope="col">Önceki Stok</th>
                <th scope="col">Yeni Stok</th>
                <th scope="col">Belge / İrsaliye</th>
                <th scope="col">İlgili Cari / Firma</th>
                <th scope="col">İşlemi Yapan</th>
                <th scope="col">Açıklama</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
            <tr>
                <td colspan="11" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
                    Seçilen kriterlere uygun stok hareketi bulunamadı.
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td>#<?= (int)$m['id'] ?></td>
                    <td><?= format_date($m['movement_date']) ?></td>
                    <td><?= get_movement_badge($m['movement_type']) ?></td>
                    <td>
                        <strong>
                            <a href="product-view.php?id=<?= (int)$m['product_id'] ?>" style="color: inherit;">
                                <?= e($m['product_name']) ?>
                            </a>
                        </strong>
                        <small class="text-muted" style="display: block;">SKU: <?= e($m['sku']) ?></small>
                    </td>
                    <td>
                        <strong>
                            <?= ($m['movement_type'] === 'in' ? '+' : ($m['movement_type'] === 'out' ? '-' : '')) . format_number($m['quantity']) ?> <?= e($m['unit']) ?>
                        </strong>
                    </td>
                    <td><?= format_number($m['previous_stock']) ?></td>
                    <td><strong><?= format_number($m['current_stock']) ?></strong></td>
                    <td><code><?= e($m['reference_no'] ?: '-') ?></code></td>
                    <td><?= e($m['supplier_name'] ?: ($m['customer_name'] ?: '-')) ?></td>
                    <td><?= e($m['user_name'] ?: 'Sistem') ?></td>
                    <td><?= e($m['note'] ?: '-') ?></td>
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
$base_url = 'stock-movements.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
echo render_pagination($total_movements, $per_page, $page, $base_url);
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
