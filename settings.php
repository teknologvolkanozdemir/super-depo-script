<?php
/**
 * Süper Depo Scripti - Sistem Ayarları ve Telegram 2FA Doğrulaması
 */

$page_title = 'Sistem & Güvenlik Ayarları';
$active_page = 'settings';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

require_role('admin');
$db = get_db();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'CSRF doğrulaması geçersiz.';
    } else {
        $action = $_POST['action'] ?? 'save_settings';

        if ($action === 'test_telegram') {
            $bot_token = trim($_POST['telegram_bot_token'] ?? '');
            $chat_id = trim($_POST['telegram_chat_id'] ?? '');

            if (empty($bot_token) || empty($chat_id)) {
                $errors[] = 'Test için Bot Token ve Chat ID alanlarını doldurmalısınız.';
            } else {
                $test_msg = "🔐 <b>" . APP_NAME . " - Güvenlik Testi</b>\n\nTelegram bildirim ve 2FA entegrasyonu başarıyla test edildi!\nTarih: " . date('d.m.Y H:i:s');
                $res = send_telegram_message($bot_token, $chat_id, $test_msg);
                if ($res['success']) {
                    set_flash('success', 'Telegram test mesajı başarıyla gönderildi! Lütfen Telegram uygulamanızı kontrol edin.');
                } else {
                    $errors[] = 'Telegram mesajı gönderilemedi: ' . ($res['error'] ?? 'Bilinmeyen hata');
                }
            }
        } elseif ($action === 'save_settings') {
            $company_name = trim($_POST['company_name'] ?? 'Süper Depo');
            $currency = trim($_POST['currency'] ?? '₺');
            $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
            $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
            $telegram_2fa_enabled = isset($_POST['telegram_2fa_enabled']) ? '1' : '0';
            $low_stock_notification = isset($_POST['low_stock_notification']) ? '1' : '0';
            $wc_store_url = trim($_POST['wc_store_url'] ?? '');
            $wc_consumer_key = trim($_POST['wc_consumer_key'] ?? '');
            $wc_consumer_secret = trim($_POST['wc_consumer_secret'] ?? '');

            set_setting('company_name', $company_name);
            set_setting('currency', $currency);
            set_setting('telegram_bot_token', $telegram_bot_token);
            set_setting('telegram_chat_id', $telegram_chat_id);
            set_setting('telegram_2fa_enabled', $telegram_2fa_enabled);
            set_setting('low_stock_notification', $low_stock_notification);
            set_setting('wc_store_url', $wc_store_url);
            set_setting('wc_consumer_key', $wc_consumer_key);
            set_setting('wc_consumer_secret', $wc_consumer_secret);

            log_activity('settings_update', 'Sistem ve güvenlik ayarları güncellendi.');
            set_flash('success', 'Tüm ayarlar başarıyla kaydedildi.');
            header('Location: settings.php');
            exit;
        }
    }
}

$company_name = get_setting('company_name', 'Süper Depo Yönetimi');
$currency = get_setting('currency', '₺');
$telegram_bot_token = get_setting('telegram_bot_token', '');
$telegram_chat_id = get_setting('telegram_chat_id', '');
$telegram_2fa_enabled = get_setting('telegram_2fa_enabled', '0');
$wc_store_url = get_setting('wc_store_url', '');
$wc_consumer_key = get_setting('wc_consumer_key', '');
$wc_consumer_secret = get_setting('wc_consumer_secret', '');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sistem & Güvenlik Ayarları</h1>
        <p class="page-subtitle">Telegram 2FA doğrulaması, genel depo ayarları ve e-ticaret bağlantıları</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem;">
    <!-- 1. Telegram Giriş Güvenliği (2FA) -->
    <div class="card" style="border-top: 4px solid var(--color-primary);">
        <div class="card-header">
            <h2 class="card-title">📱 Telegram ile Giriş Güvenliği (2FA)</h2>
        </div>
        <p class="form-hint" style="margin-bottom: 1rem;">
            Giriş güvenliğini artırmak için Telegram iki adımlı doğrulama (2FA) kodunu etkinleştirebilirsiniz. Giriş yapan personele Telegram üzerinden anlık 6 haneli güvenlik kodu iletilir.
        </p>

        <form action="settings.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_settings">

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: bold; cursor: pointer;">
                    <input type="checkbox" name="telegram_2fa_enabled" value="1" <?= $telegram_2fa_enabled === '1' ? 'checked' : '' ?>>
                    <span>Telegram 2FA İki Adımlı Doğrulamayı Etkinleştir</span>
                </label>
                <div class="form-hint">İşaretlendiğinde, doğru şifre girildikten sonra Telegram'a doğrulama kodu gönderilir.</div>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: bold; cursor: pointer;">
                    <input type="checkbox" name="low_stock_notification" value="1" <?= get_setting('low_stock_notification', '1') === '1' ? 'checked' : '' ?>>
                    <span>Kritik ve Biten Stok Bildirimlerini Telegram'a Gönder</span>
                </label>
                <div class="form-hint">Ürün stoğu kritik eşiğin altına indiğinde anlık uyarı mesajı iletilir.</div>
            </div>

            <div class="form-group">
                <label for="telegram_bot_token" class="form-label">Telegram Bot Token:</label>
                <input type="text" id="telegram_bot_token" name="telegram_bot_token" class="form-control" 
                       value="<?= e($telegram_bot_token) ?>" placeholder="Örn: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                <div class="form-hint">@BotFather üzerinden aldığınız bot erişim belirteci (token).</div>
            </div>

            <div class="form-group">
                <label for="telegram_chat_id" class="form-label">Telegram Chat ID:</label>
                <input type="text" id="telegram_chat_id" name="telegram_chat_id" class="form-control" 
                       value="<?= e($telegram_chat_id) ?>" placeholder="Örn: 987654321">
                <div class="form-hint">Kodun gönderileceği Telegram kullanıcı veya grup ID numarası (örn: @userinfobot ile öğrenilebilir).</div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                <button type="submit" formaction="settings.php" name="action" value="test_telegram" class="btn btn-outline">
                    🔔 Test Mesajı Gönder
                </button>
            </div>
        </form>
    </div>

    <!-- 2. Genel Şirket & Depo Ayarları -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏢 Genel Depo Ayarları</h2>
        </div>
        <form action="settings.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_settings">
            <!-- Telegram alanlarını koru -->
            <input type="hidden" name="telegram_bot_token" value="<?= e($telegram_bot_token) ?>">
            <input type="hidden" name="telegram_chat_id" value="<?= e($telegram_chat_id) ?>">
            <?php if ($telegram_2fa_enabled === '1'): ?>
                <input type="hidden" name="telegram_2fa_enabled" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="company_name" class="form-label">Depo / Şirket Adı:</label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= e($company_name) ?>" required>
            </div>

            <div class="form-group">
                <label for="currency" class="form-label">Para Birimi Sembolü:</label>
                <input type="text" id="currency" name="currency" class="form-control" value="<?= e($currency) ?>" required>
            </div>

            <div class="form-group">
                <label for="wc_store_url" class="form-label">WooCommerce Mağaza Adresi:</label>
                <input type="url" id="wc_store_url" name="wc_store_url" class="form-control" value="<?= e($wc_store_url) ?>" placeholder="https://magaza.com">
            </div>

            <div class="form-group">
                <label for="wc_consumer_key" class="form-label">WooCommerce Consumer Key:</label>
                <input type="text" id="wc_consumer_key" name="wc_consumer_key" class="form-control" value="<?= e($wc_consumer_key) ?>" placeholder="ck_...">
            </div>

            <div class="form-group">
                <label for="wc_consumer_secret" class="form-label">WooCommerce Consumer Secret:</label>
                <input type="password" id="wc_consumer_secret" name="wc_consumer_secret" class="form-control" value="<?= e($wc_consumer_secret) ?>" placeholder="cs_...">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Kaydet</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
