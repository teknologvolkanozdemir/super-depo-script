<?php
/**
 * Süper Depo Scripti - Telegram 2FA İki Adımlı Doğrulama Ekranı
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['2fa_pending'])) {
    header('Location: login.php');
    exit;
}

$pending = &$_SESSION['2fa_pending'];
$error = '';
$success = '';

// Yeniden kod gönderme
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $new_code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $pending['code'] = $new_code;
    $pending['expires'] = time() + 300;
    $pending['attempts'] = 0;

    $bot_token = get_setting('telegram_bot_token', '');
    $chat_id = get_setting('telegram_chat_id', '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $msg = "🔄 <b>" . APP_NAME . " - Yeni 2FA Doğrulama Kodu</b>\n\nSayın <b>" . htmlspecialchars($pending['user']['full_name']) . "</b>,\nYeni doğrulama kodunuz:\n\n👉 <code>{$new_code}</code>\n\nIP: {$ip}\nSüre: 5 dakika.";
    send_telegram_message($bot_token, $chat_id, $msg);
    $success = 'Yeni doğrulama kodu Telegram üzerinden gönderildi.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'CSRF doğrulaması başarısız oldu.';
    } else {
        $entered_code = trim($_POST['code'] ?? '');

        if (time() > $pending['expires']) {
            $error = 'Doğrulama kodunun süresi dolmuş. Lütfen yeni kod isteyin.';
        } elseif ($pending['attempts'] >= 5) {
            unset($_SESSION['2fa_pending']);
            set_flash('danger', 'Çok fazla hatalı kod girdiniz. Lütfen tekrar giriş yapın.');
            header('Location: login.php');
            exit;
        } elseif (hash_equals($pending['code'], $entered_code)) {
            $user = $pending['user'];
            unset($_SESSION['2fa_pending']);
            login_user($user);
            log_activity('login_2fa', 'Kullanıcı Telegram 2FA ile giriş yaptı: ' . $user['username']);
            set_flash('success', 'Giriş başarılı! Hoş geldiniz, ' . $user['full_name']);
            header('Location: index.php');
            exit;
        } else {
            $pending['attempts']++;
            $remaining = 5 - $pending['attempts'];
            $error = "Hatalı doğrulama kodu! Kalan deneme hakkı: {$remaining}";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram 2FA Doğrulama - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <main class="auth-card" tabindex="-1">
            <div class="auth-logo">
                <div class="brand-icon" style="margin: 0 auto; width: 60px; height: 60px; font-size: 2rem;" aria-hidden="true">📱</div>
                <h1>İki Adımlı Doğrulama</h1>
                <p class="text-muted">Telegram hesabınıza gönderilen 6 haneli kodu giriniz</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive">
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <div><?= e($success) ?></div>
                </div>
            <?php endif; ?>

            <form action="verify-2fa.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="code" class="form-label" style="text-align: center; display: block; font-size: 1.1rem;">
                        6 Haneli Güvenlik Kodu <span class="form-required">*</span>
                    </label>
                    <input type="text" id="code" name="code" class="form-control" 
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" 
                           autocomplete="one-time-code" autofocus required 
                           style="font-size: 1.75rem; letter-spacing: 0.5rem; text-align: center; font-weight: bold; font-family: monospace;"
                           placeholder="000000">
                    <div class="form-hint" style="text-align: center;">Kodun geçerlilik süresi 5 dakikadır.</div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; padding: 0.75rem;">
                    Kodu Doğrula ve Giriş Yap
                </button>
            </form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem;">
                <p>Kod gelmedi mi? <a href="verify-2fa.php?resend=1" style="font-weight: bold;">Yeniden Kod Gönder</a></p>
                <p style="margin-top: 0.5rem;"><a href="login.php" style="color: var(--color-text-muted);">Giriş Ekranına Dön</a></p>
            </div>
        </main>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
