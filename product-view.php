<?php
/**
 * Süper Depo Scripti - Ürün Detayı, Hareketleri ve Barkod Etiketi
 */

$page_title = 'Ürün Detayı';
$active_page = 'products';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT p.*, c.name as category_name, l.name as location_name, l.warehouse_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN locations l ON p.location_id = l.id 
                      WHERE p.id = ? AND p.is_active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Ürün bulunamadı.');
    header('Location: products.php');
    exit;
}

// Ürünün Stok Hareketleri
$stmt_mov = $db->prepare('SELECT sm.*, u.full_name as user_name, s.name as supplier_name, c.name as customer_name 
                          FROM stock_movements sm 
                          LEFT JOIN users u ON sm.user_id = u.id 
                          LEFT JOIN suppliers s ON sm.supplier_id = s.id 
                          LEFT JOIN customers c ON sm.customer_id = c.id 
                          WHERE sm.product_id = ? 
                          ORDER BY sm.id DESC');
$stmt_mov->execute([$id]);
$movements = $stmt_mov->fetchAll();

// Toplam Stok Maliyeti ve Satış Değeri
$cost_value = (float)$product['stock_quantity'] * (float)$product['purchase_price'];
$sale_value = (float)$product['stock_quantity'] * (float)$product['sale_price'];
?>

<!-- Yazdırma İçin Barkod & Raf Etiketi Görünümü (Yalnızca Print modunda veya Etiket Önizlemesinde) -->
<div class="print-only" style="padding: 20px; border: 2px dashed #000; max-width: 400px; margin: 0 auto; text-align: center;">
    <h2 style="font-size: 16pt; margin-bottom: 5px;"><?= e(APP_NAME) ?></h2>
    <div style="font-size: 18pt; font-weight: bold; margin: 10px 0;"><?= e($product['name']) ?></div>
    <div style="font-size: 12pt; margin: 5px 0;"><strong>SKU:</strong> <?= e($product['sku']) ?></div>
    <?php if ($product['barcode']): ?>
        <div style="font-family: monospace; font-size: 20pt; letter-spacing: 4px; padding: 10px 0; border: 1px solid #000; margin: 10px 0;">
            ||| | |||| | ||| |||| |<br>
            <?= e($product['barcode']) ?>
        </div>
    <?php endif; ?>
    <div style="font-size: 14pt; margin-top: 5px;">
        <strong>Konum:</strong> <?= e($product['warehouse_name']) ?> - <?= e($product['location_name'] ?: 'Tanımsız') ?>
    </div>
    <div style="font-size: 16pt; font-weight: bold; margin-top: 10px;">
        Fiyat: <?= format_currency($product['sale_price']) ?>
    </div>
</div>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><?= e($product['name']) ?></h1>
        <p class="page-subtitle">SKU: <code><?= e($product['sku']) ?></code> | Barkod: <code><?= e($product['barcode'] ?: 'Yok') ?></code></p>
    </div>
    <div class="page-actions">
        <button type="button" onclick="window.print()" class="btn btn-outline" title="Depo Raf / Barkod Etiketini Yazdır">
            <span aria-hidden="true">🖨️</span> Etiket Yazdır
        </button>
        <a href="stock-in.php?product_id=<?= $id ?>" class="btn btn-success" title="Depoya bu üründen giriş yap">
            <span aria-hidden="true">📥</span> Stok Girişi
        </a>
        <a href="stock-out.php?product_id=<?= $id ?>" class="btn btn-danger" title="Depodan bu ürünü sevk et">
            <span aria-hidden="true">📤</span> Stok Çıkışı
        </a>
        <a href="product-edit.php?id=<?= $id ?>" class="btn btn-primary" title="Ürün Bilgilerini Düzenle">
            <span aria-hidden="true">✏️</span> Düzenle
        </a>
        <a href="products.php" class="btn btn-outline">&laquo; Listeye Dön</a>
    </div>
</div>

<!-- Ürün Bilgi Kartı -->
<div class="stats-grid no-print">
    <div class="stat-card">
        <div class="stat-icon info" aria-hidden="true">📦</div>
        <div>
            <div class="stat-label">Mevcut Stok Miktarı</div>
            <div class="stat-value"><?= format_number($product['stock_quantity']) ?> <?= e($product['unit']) ?></div>
            <div style="margin-top: 4px;">
                <?= get_stock_badge((float)$product['stock_quantity'], (float)$product['min_stock_level']) ?>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon primary" aria-hidden="true">📍</div>
        <div>
            <div class="stat-label">Depo & Raf Konumu</div>
            <div class="stat-value" style="font-size: 1.15rem;"><?= e($product['location_name'] ?: 'Tanımsız') ?></div>
            <small class="text-muted"><?= e($product['warehouse_name']) ?></small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success" aria-hidden="true">💵</div>
        <div>
            <div class="stat-label">Birim Fiyatlar</div>
            <div class="stat-value" style="font-size: 1.15rem;">Alış: <?= format_currency($product['purchase_price']) ?></div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-success);">Satış: <?= format_currency($product['sale_price']) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning" aria-hidden="true">📊</div>
        <div>
            <div class="stat-label">Toplam Envanter Değeri</div>
            <div class="stat-value" style="font-size: 1.15rem;"><?= format_currency($cost_value) ?> (Maliyet)</div>
            <small class="text-muted">Satış Değeri: <?= format_currency($sale_value) ?></small>
        </div>
    </div>
</div>

<div class="card no-print">
    <div class="card-header">
        <h2 class="card-title">Ürün Detayları ve Özellikleri</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <div><strong>Kategori:</strong> <?= e($product['category_name'] ?: '-') ?></div>
        <div><strong>Kritik Stok Uyarı Seviyesi:</strong> <?= format_number($product['min_stock_level']) ?> <?= e($product['unit']) ?></div>
        <div><strong>Kayıt Tarihi:</strong> <?= format_datetime($product['created_at']) ?></div>
        <div><strong>Son Güncelleme:</strong> <?= format_datetime($product['updated_at']) ?></div>
    </div>
    <?php if (!empty($product['description'])): ?>
    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
        <strong>Açıklama:</strong>
        <p style="margin-top: 0.25rem; white-space: pre-wrap;"><?= e($product['description']) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Stok Hareket Geçmişi -->
<div class="card no-print">
    <div class="card-header">
        <h2 class="card-title">Bu Ürüne Ait Stok Hareket Geçmişi (<?= count($movements) ?> İşlem)</h2>
        <a href="stock-movements.php?product_id=<?= $id ?>" class="btn btn-sm btn-outline">Tüm Filtreler</a>
    </div>

    <div class="table-responsive">
        <table class="data-table" aria-label="Ürün Stok Hareketleri">
            <thead>
                <tr>
                    <th scope="col">Tarih</th>
                    <th scope="col">İşlem</th>
                    <th scope="col">Miktar</th>
                    <th scope="col">Birim Maliyet/Fiyat</th>
                    <th scope="col">Önceki Stok</th>
                    <th scope="col">Sonraki Stok</th>
                    <th scope="col">İrsaliye / Belge No</th>
                    <th scope="col">Tedarikçi / Müşteri</th>
                    <th scope="col">İşlemi Yapan</th>
                    <th scope="col">Açıklama</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                        Bu ürüne ait henüz stok hareketi bulunmuyor.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                    <tr>
                        <td><?= format_date($m['movement_date']) ?></td>
                        <td><?= get_movement_badge($m['movement_type']) ?></td>
                        <td>
                            <strong>
                                <?= ($m['movement_type'] === 'in' ? '+' : ($m['movement_type'] === 'out' ? '-' : '')) . format_number($m['quantity']) ?> <?= e($product['unit']) ?>
                            </strong>
                        </td>
                        <td><?= format_currency($m['unit_price']) ?></td>
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
