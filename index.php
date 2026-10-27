<?php
/**
 * Süper Depo Scripti - Ana Kontrol Paneli (Dashboard)
 */

$page_title = 'Kontrol Paneli';
$active_page = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();

// İstatistikler
// 1. Toplam ürün çeşidi
$total_products = $db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

// 2. Toplam stok adedi ve toplam değer
$stock_stats = $db->query('SELECT 
    COALESCE(SUM(stock_quantity), 0) as total_qty,
    COALESCE(SUM(stock_quantity * purchase_price), 0) as total_cost_value,
    COALESCE(SUM(stock_quantity * sale_price), 0) as total_sale_value
    FROM products WHERE is_active = 1')->fetch();

// 3. Kritik seviyedeki ürünler (stok <= min_stock_level)
$critical_count = $db->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity <= min_stock_level')->fetchColumn();

// 4. Kritik stok listesi
$stmt_crit = $db->query('SELECT p.*, c.name as category_name, l.name as location_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN locations l ON p.location_id = l.id 
    WHERE p.is_active = 1 AND p.stock_quantity <= p.min_stock_level 
    ORDER BY p.stock_quantity ASC LIMIT 5');
$critical_products = $stmt_crit->fetchAll();

// 5. Son 10 Stok Hareketi
$stmt_recent = $db->query('SELECT sm.*, p.name as product_name, p.unit, p.sku, u.full_name as user_name 
    FROM stock_movements sm 
    JOIN products p ON sm.product_id = p.id 
    LEFT JOIN users u ON sm.user_id = u.id 
    ORDER BY sm.id DESC LIMIT 10');
$recent_movements = $stmt_recent->fetchAll();

// 6. Bugünkü Giriş ve Çıkışlar
$today = date('Y-m-d');
$stmt_today = $db->prepare('SELECT movement_type, COALESCE(SUM(quantity), 0) as total_qty 
    FROM stock_movements WHERE movement_date = ? GROUP BY movement_type');
$stmt_today->execute([$today]);
$today_stats = ['in' => 0, 'out' => 0];
while ($row = $stmt_today->fetch()) {
    $today_stats[$row['movement_type']] = (float)$row['total_qty'];
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depo Genel Bakış</h1>
        <p class="page-subtitle">Depo durumu, güncel stok seviyeleri ve anlık hareket özetleri</p>
    </div>
    <div class="page-actions">
        <a href="stock-in.php" class="btn btn-success" title="Depoya Mal Kabul / Alış (Alt+G)">
            <span aria-hidden="true">📥</span> Ürün Girişi Yap
        </a>
        <a href="stock-out.php" class="btn btn-danger" title="Depodan Çıkış / Sevkiyat (Alt+C)">
            <span aria-hidden="true">📤</span> Ürün Çıkışı Yap
        </a>
        <a href="product-add.php" class="btn btn-primary" title="Yeni Ürün Kartı Tanımla (Alt+N)">
            <span aria-hidden="true">➕</span> Yeni Ürün
        </a>
    </div>
</div>

<!-- İstatistik Kartları -->
<section aria-label="Genel Depo İstatistikleri">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary" aria-hidden="true">📦</div>
            <div>
                <div class="stat-label">Toplam Ürün Çeşidi</div>
                <div class="stat-value"><?= format_number($total_products, 0) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info" aria-hidden="true">📊</div>
            <div>
                <div class="stat-label">Mevcut Toplam Stok</div>
                <div class="stat-value"><?= format_number($stock_stats['total_qty'], 0) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success" aria-hidden="true">💰</div>
            <div>
                <div class="stat-label">Toplam Stok Maliyeti</div>
                <div class="stat-value"><?= format_currency($stock_stats['total_cost_value']) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon <?= $critical_count > 0 ? 'danger' : 'success' ?>" aria-hidden="true">⚠</div>
            <div>
                <div class="stat-label">Kritik / Biten Stoklar</div>
                <div class="stat-value"><?= format_number($critical_count, 0) ?></div>
            </div>
        </div>
    </div>
</section>

<!-- Kritik Stok Uyarı Alanı -->
<?php if ($critical_count > 0): ?>
<div class="card" style="border-left: 5px solid var(--color-warning);">
    <div class="card-header">
        <h2 class="card-title" style="color: var(--color-warning); display: flex; align-items: center; gap: 0.5rem;">
            <span aria-hidden="true">⚠</span> Kritik Stok Seviyesindeki Ürünler (<?= $critical_count ?> Adet)
        </h2>
        <a href="reports.php?filter=low_stock" class="btn btn-sm btn-outline">Tümünü İncele</a>
    </div>
    <div class="table-responsive">
        <table class="data-table" aria-label="Kritik Stok Tablosu">
            <thead>
                <tr>
                    <th scope="col">SKU / Barkod</th>
                    <th scope="col">Ürün Adı</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Konum</th>
                    <th scope="col">Mevcut Stok</th>
                    <th scope="col">Kritik Eşik</th>
                    <th scope="col" style="text-align: right;">Hızlı İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($critical_products as $cp): ?>
                <tr>
                    <td><code><?= e($cp['sku']) ?></code></td>
                    <td><strong><?= e($cp['name']) ?></strong></td>
                    <td><?= e($cp['category_name'] ?: '-') ?></td>
                    <td><?= e($cp['location_name'] ?: '-') ?></td>
                    <td><?= get_stock_badge((float)$cp['stock_quantity'], (float)$cp['min_stock_level']) ?></td>
                    <td><?= format_number($cp['min_stock_level']) ?> <?= e($cp['unit']) ?></td>
                    <td style="text-align: right;">
                        <a href="stock-in.php?product_id=<?= (int)$cp['id'] ?>" class="btn btn-sm btn-success" title="Depoya bu üründen giriş yap">
                            + Stok Ekle
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Son Stok Hareketleri -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Son Gerçekleşen Stok Hareketleri</h2>
        <a href="stock-movements.php" class="btn btn-sm btn-outline">Tüm Hareket Geçmişi &raquo;</a>
    </div>
    
    <div class="table-responsive">
        <table class="data-table" aria-label="Son Stok Hareketleri">
            <thead>
                <tr>
                    <th scope="col">Tarih</th>
                    <th scope="col">İşlem Tipi</th>
                    <th scope="col">Ürün Bilgisi</th>
                    <th scope="col">Miktar</th>
                    <th scope="col">İşlem Öncesi</th>
                    <th scope="col">Güncel Stok</th>
                    <th scope="col">Belge / İrsaliye No</th>
                    <th scope="col">Kullanıcı</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_movements)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                        Henüz kaydedilmiş stok hareketi bulunmuyor.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recent_movements as $m): ?>
                    <tr>
                        <td><?= format_date($m['movement_date']) ?></td>
                        <td><?= get_movement_badge($m['movement_type']) ?></td>
                        <td>
                            <a href="product-view.php?id=<?= (int)$m['product_id'] ?>" style="font-weight: 600; color: inherit;">
                                <?= e($m['product_name']) ?>
                            </a>
                            <small class="text-muted" style="display: block;">SKU: <?= e($m['sku']) ?></small>
                        </td>
                        <td>
                            <strong><?= ($m['movement_type'] === 'in' ? '+' : ($m['movement_type'] === 'out' ? '-' : '')) . format_number($m['quantity']) ?> <?= e($m['unit']) ?></strong>
                        </td>
                        <td><?= format_number($m['previous_stock']) ?> <?= e($m['unit']) ?></td>
                        <td><?= format_number($m['current_stock']) ?> <?= e($m['unit']) ?></td>
                        <td><code><?= e($m['reference_no'] ?: '-') ?></code></td>
                        <td><?= e($m['user_name'] ?: 'Sistem') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
