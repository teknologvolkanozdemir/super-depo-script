<?php
/**
 * Süper Depo Scripti - Raporlama ve Dışa Aktarma (CSV/Excel)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$db = get_db();

// CSV Dışa Aktarma İstekleri
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type === 'products_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="depo_urunler_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM Excel için

        fputcsv($output, ['ID', 'SKU', 'Barkod', 'Ürün Adı', 'Kategori', 'Depo Konumu', 'Birim', 'Alış Fiyatı (₺)', 'Satış Fiyatı (₺)', 'Stok Miktarı', 'Kritik Eşik', 'Toplam Maliyet Değeri (₺)', 'Toplam Satış Değeri (₺)']);

        $stmt = $db->query('SELECT p.*, c.name as category_name, l.name as location_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            LEFT JOIN locations l ON p.location_id = l.id 
                            WHERE p.is_active = 1 ORDER BY p.name ASC');
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['sku'],
                $row['barcode'] ?: '',
                $row['name'],
                $row['category_name'] ?: '-',
                $row['location_name'] ?: '-',
                $row['unit'],
                $row['purchase_price'],
                $row['sale_price'],
                $row['stock_quantity'],
                $row['min_stock_level'],
                $row['stock_quantity'] * $row['purchase_price'],
                $row['stock_quantity'] * $row['sale_price']
            ]);
        }
        fclose($output);
        exit;
    }

    if ($export_type === 'movements_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stok_hareketleri_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['İşlem No', 'Tarih', 'İşlem Türü', 'Ürün Adı', 'SKU', 'Miktar', 'Birim', 'Birim Fiyat', 'Önceki Stok', 'Yeni Stok', 'Belge No', 'Açıklama']);

        $stmt = $db->query('SELECT sm.*, p.name as product_name, p.sku, p.unit 
                            FROM stock_movements sm 
                            JOIN products p ON sm.product_id = p.id 
                            ORDER BY sm.id DESC');
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['movement_date'],
                $row['movement_type'],
                $row['product_name'],
                $row['sku'],
                $row['quantity'],
                $row['unit'],
                $row['unit_price'],
                $row['previous_stock'],
                $row['current_stock'],
                $row['reference_no'] ?: '',
                $row['note'] ?: ''
            ]);
        }
        fclose($output);
        exit;
    }
}

$page_title = 'Raporlar ve Envanter Analizi';
$active_page = 'reports';
require_once __DIR__ . '/includes/header.php';

// Rapor İstatistikleri
// 1. Kategori bazlı envanter değerleri
$category_report = $db->query('SELECT c.name as category_name, 
    COUNT(p.id) as product_count, 
    COALESCE(SUM(p.stock_quantity), 0) as total_qty, 
    COALESCE(SUM(p.stock_quantity * p.purchase_price), 0) as total_cost,
    COALESCE(SUM(p.stock_quantity * p.sale_price), 0) as total_sale
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
    GROUP BY c.id ORDER BY total_cost DESC')->fetchAll();

// 2. Kritik ve Tükenen Ürünler Listesi
$crit_stmt = $db->query('SELECT p.*, c.name as category_name, l.name as location_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         LEFT JOIN locations l ON p.location_id = l.id 
                         WHERE p.is_active = 1 AND p.stock_quantity <= p.min_stock_level 
                         ORDER BY p.stock_quantity ASC');
$critical_list = $crit_stmt->fetchAll();

// 3. En çok sevk edilen / çıkışı yapılan ilk 5 ürün
$top_out = $db->query('SELECT p.name, p.sku, p.unit, SUM(sm.quantity) as total_out_qty, SUM(sm.quantity * sm.unit_price) as total_out_val 
                       FROM stock_movements sm 
                       JOIN products p ON sm.product_id = p.id 
                       WHERE sm.movement_type = "out" 
                       GROUP BY p.id ORDER BY total_out_qty DESC LIMIT 5')->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depo Raporları ve Envanter Analizi</h1>
        <p class="page-subtitle">Stok değerleri, kategori analizleri ve kritik stok durum raporları</p>
    </div>
    <div class="page-actions no-print">
        <button type="button" onclick="window.print()" class="btn btn-outline">
            🖨️ Sayfayı Yazdır
        </button>
        <a href="reports.php?export=products_csv" class="btn btn-success">
            📥 Ürün Envanterini CSV İndir
        </a>
        <a href="reports.php?export=movements_csv" class="btn btn-primary">
            📥 Hareket Geçmişini CSV İndir
        </a>
    </div>
</div>

<!-- Kategori Bazlı Envanter Değerleri -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Kategori Bazlı Stok ve Maliyet Değer Analizi</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table" aria-label="Kategori Raporu">
            <thead>
                <tr>
                    <th scope="col">Kategori</th>
                    <th scope="col">Ürün Çeşidi</th>
                    <th scope="col">Toplam Stok Miktarı</th>
                    <th scope="col">Toplam Satın Alma Maliyeti</th>
                    <th scope="col">Toplam Satış Değeri</th>
                    <th scope="col">Potansiyel Kâr Brütü</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sum_qty = 0; $sum_cost = 0; $sum_sale = 0;
                foreach ($category_report as $cr): 
                    $sum_qty += (float)$cr['total_qty'];
                    $sum_cost += (float)$cr['total_cost'];
                    $sum_sale += (float)$cr['total_sale'];
                    $profit = (float)$cr['total_sale'] - (float)$cr['total_cost'];
                ?>
                <tr>
                    <td><strong><?= e($cr['category_name']) ?></strong></td>
                    <td><?= $cr['product_count'] ?> Çeşit</td>
                    <td><?= format_number($cr['total_qty']) ?></td>
                    <td><?= format_currency($cr['total_cost']) ?></td>
                    <td><strong><?= format_currency($cr['total_sale']) ?></strong></td>
                    <td style="color: <?= $profit >= 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                        <?= format_currency($profit) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: var(--color-surface-hover); font-weight: bold;">
                    <td>GENEL TOPLAM</td>
                    <td>-</td>
                    <td><?= format_number($sum_qty) ?></td>
                    <td><?= format_currency($sum_cost) ?></td>
                    <td><?= format_currency($sum_sale) ?></td>
                    <td style="color: var(--color-success);"><?= format_currency($sum_sale - $sum_cost) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Kritik Stoktaki Ürünler Detay Raporu -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title" style="color: var(--color-danger);">
            ⚠ Kritik ve Tükenen Stoklar Raporu (<?= count($critical_list) ?> Ürün)
        </h2>
    </div>
    <div class="table-responsive">
        <table class="data-table" aria-label="Kritik Stok Raporu">
            <thead>
                <tr>
                    <th scope="col">SKU</th>
                    <th scope="col">Ürün Adı</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Depo Konumu</th>
                    <th scope="col">Mevcut Stok</th>
                    <th scope="col">Kritik Eşik</th>
                    <th scope="col">Sipariş Önerisi</th>
                    <th scope="col" class="no-print" style="text-align: right;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($critical_list)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--color-success); padding: 1.5rem;">
                        Tebrikler! Kritik seviyede veya tükenmiş ürün bulunmuyor.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($critical_list as $cl): 
                        $deficit = max(0, (float)$cl['min_stock_level'] * 2 - (float)$cl['stock_quantity']);
                    ?>
                    <tr>
                        <td><code><?= e($cl['sku']) ?></code></td>
                        <td><strong><?= e($cl['name']) ?></strong></td>
                        <td><?= e($cl['category_name'] ?: '-') ?></td>
                        <td><?= e($cl['location_name'] ?: '-') ?></td>
                        <td><?= get_stock_badge((float)$cl['stock_quantity'], (float)$cl['min_stock_level']) ?></td>
                        <td><?= format_number($cl['min_stock_level']) ?> <?= e($cl['unit']) ?></td>
                        <td><strong>+<?= format_number($deficit) ?> <?= e($cl['unit']) ?></strong> sipariş verilmeli</td>
                        <td class="no-print" style="text-align: right;">
                            <a href="stock-in.php?product_id=<?= (int)$cl['id'] ?>" class="btn btn-sm btn-success">+ Stok Ekle</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- En Çok Çıkışı Yapılan Ürünler -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">En Çok Çıkışı Yapılan (Sevkiyatı Gerçekleşen) İlk 5 Ürün</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table" aria-label="En Çok Çıkan Ürünler">
            <thead>
                <tr>
                    <th scope="col">Ürün Adı</th>
                    <th scope="col">SKU</th>
                    <th scope="col">Toplam Sevk Edilen Miktar</th>
                    <th scope="col">Toplam Sevkiyat Tutarı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_out as $to): ?>
                <tr>
                    <td><strong><?= e($to['name']) ?></strong></td>
                    <td><code><?= e($to['sku']) ?></code></td>
                    <td><strong><?= format_number($to['total_out_qty']) ?> <?= e($to['unit']) ?></strong></td>
                    <td><?= format_currency($to['total_out_val']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
