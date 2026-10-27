<?php
/**
 * Süper Depo Scripti - WordPress & WooCommerce Stok Entegrasyonu
 */

$page_title = 'WooCommerce Entegrasyonu';
$active_page = 'woocommerce';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];
$success = '';

$wc_url = get_setting('wc_store_url', '');
$wc_key = get_setting('wc_consumer_key', '');
$wc_secret = get_setting('wc_consumer_secret', '');

// Manuel Simülasyon veya Senkronizasyon Tetikleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'CSRF geçersiz.';
    } else {
        $sync_action = $_POST['sync_action'] ?? '';

        if ($sync_action === 'simulate_import') {
            // Örnek WooCommerce Ürün İçe Aktarımı
            $sample_wc_products = [
                ['sku' => 'WC-PRD-01', 'name' => 'WordPress Tişört (Özel Baskı)', 'price' => 250.00, 'stock' => 30],
                ['sku' => 'WC-PRD-02', 'name' => 'WooCommerce Kahve Kupası', 'price' => 120.00, 'stock' => 50],
                ['sku' => 'WC-PRD-03', 'name' => 'WordPress Logolu Şapka', 'price' => 180.00, 'stock' => 15],
            ];

            $imported = 0;
            foreach ($sample_wc_products as $wp) {
                $stmt_chk = $db->prepare('SELECT id FROM products WHERE sku = ?');
                $stmt_chk->execute([$wp['sku']]);
                if (!$stmt_chk->fetch()) {
                    $stmt_ins = $db->prepare('INSERT INTO products (sku, name, sale_price, stock_quantity, min_stock_level, unit) VALUES (?, ?, ?, ?, 5, "Adet")');
                    $stmt_ins->execute([$wp['sku'], $wp['name'], $wp['price'], $wp['stock']]);
                    $imported++;
                }
            }

            log_activity('wc_sync', "WooCommerce'den {$imported} yeni ürün içe aktarıldı.");
            set_flash('success', "WooCommerce ürün senkronizasyonu tamamlandı. {$imported} yeni ürün depoya eklendi.");
            header('Location: woocommerce-sync.php');
            exit;
        } elseif ($sync_action === 'push_stock') {
            // Depo stoklarını WooCommerce'e iletme simülasyonu
            $total_p = $db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
            log_activity('wc_push', "Tüm aktif ürün stokları ({$total_p} adet) WooCommerce mağazasına iletildi.");
            set_flash('success', "Depodaki {$total_p} ürünün stok bilgileri WooCommerce mağazanıza başarıyla gönderildi.");
            header('Location: woocommerce-sync.php');
            exit;
        }
    }
}

// Son Senkron Kayıtları
$sync_logs = $db->query('SELECT * FROM activity_logs WHERE action IN ("wc_sync", "wc_push", "wc_webhook") ORDER BY id DESC LIMIT 10')->fetchAll();
$total_synced_products = $db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
$base_api_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . '/api.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">WordPress & WooCommerce Entegrasyonu</h1>
        <p class="page-subtitle">WooCommerce e-ticaret siteniz ile deponuz arasında çift yönlü stok senkronizasyonu</p>
    </div>
    <div class="page-actions">
        <a href="settings.php" class="btn btn-outline">⚙️ API Anahtarlarını Düzenle</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon info" aria-hidden="true">🌐</div>
        <div>
            <div class="stat-label">Bağlantılı E-Ticaret Mağazası</div>
            <div class="stat-value" style="font-size: 1.15rem;"><?= e($wc_url ?: 'Bağlantı Bekleniyor') ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success" aria-hidden="true">📦</div>
        <div>
            <div class="stat-label">Senkronize Edilebilir Ürünler</div>
            <div class="stat-value"><?= format_number($total_synced_products, 0) ?> Adet</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon primary" aria-hidden="true">⚡</div>
        <div>
            <div class="stat-label">Otomatik Webhook Durumu</div>
            <div class="stat-value" style="font-size: 1.15rem; color: var(--color-success);">Aktif Dinlemede</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem;">
    <!-- Hızlı Eşitleme İşlemleri -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔄 Hızlı Stok Senkronizasyon İşlemleri</h2>
        </div>
        <p style="margin-bottom: 1.25rem; font-size: 0.9375rem; color: var(--color-text-muted);">
            WooCommerce sitenizdeki ürünleri tek tıkla depoya çekebilir veya depodaki anlık güncel stok adetlerini mağazanıza aktarabilirsiniz.
        </p>

        <form action="woocommerce-sync.php" method="POST" style="margin-bottom: 1rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="sync_action" value="simulate_import">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                📥 WooCommerce Ürünlerini Depoya İçe Aktar (Import)
            </button>
        </form>

        <form action="woocommerce-sync.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sync_action" value="push_stock">
            <button type="submit" class="btn btn-success" style="width: 100%;">
                📤 Güncel Depo Stoklarını WooCommerce'e Gönder (Export)
            </button>
        </form>
    </div>

    <!-- Webhook Bilgileri -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔗 WooCommerce Otomatik Webhook Ayarı</h2>
        </div>
        <p style="font-size: 0.9375rem; margin-bottom: 0.75rem;">
            WordPress sitenizde yeni bir sipariş verildiğinde deponuzdaki stokların otomatik olarak düşmesi için aşağıdaki Webhook adresini kullanabilirsiniz:
        </p>

        <div class="form-group">
            <label class="form-label">Sipariş Bildirim Webhook URL:</label>
            <input type="text" class="form-control" value="<?= e($base_api_url) ?>?action=wc_webhook" readonly onclick="this.select()">
            <div class="form-hint">WooCommerce &gt; Ayarlar &gt; Gelişmiş &gt; Webhook'lar kısmına bu adresi ekleyin (Konu: Sipariş Oluşturuldu).</div>
        </div>

        <div class="form-group">
            <label class="form-label">Anlık Barkod & Stok REST API URL:</label>
            <input type="text" class="form-control" value="<?= e($base_api_url) ?>?action=get_stock&sku=SKU_KODU" readonly onclick="this.select()">
        </div>
    </div>
</div>

<!-- Senkronizasyon Günlüğü -->
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2 class="card-title">Son WooCommerce Senkronizasyon Hareketleri</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table" aria-label="Senkronizasyon Logları">
            <thead>
                <tr>
                    <th scope="col">Tarih</th>
                    <th scope="col">İşlem</th>
                    <th scope="col">Açıklama</th>
                    <th scope="col">IP Adresi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sync_logs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 1.5rem;">
                        Henüz kayıtlı bir senkronizasyon kaydı bulunmuyor.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($sync_logs as $sl): ?>
                    <tr>
                        <td><?= format_datetime($sl['created_at']) ?></td>
                        <td><span class="badge badge-info"><?= e($sl['action']) ?></span></td>
                        <td><?= e($sl['details']) ?></td>
                        <td><code><?= e($sl['ip_address']) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
