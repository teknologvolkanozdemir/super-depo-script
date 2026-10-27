<?php
/**
 * Süper Depo Scripti - Erişilebilir Giriş Sayfası
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Lütfen kullanıcı adı ve şifrenizi giriniz.';
        } else {
            try {
                $db = get_db();
                $stmt = $db->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1');
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $telegram_2fa = get_setting('telegram_2fa_enabled', '0') === '1';
                    $bot_token = get_setting('telegram_bot_token', '');
                    $chat_id = get_setting('telegram_chat_id', '');

                    if ($telegram_2fa && !empty($bot_token) && !empty($chat_id)) {
                        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $_SESSION['2fa_pending'] = [
                            'user' => $user,
                            'code' => $code,
                            'expires' => time() + 300, // 5 dakika
                            'attempts' => 0
                        ];

                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $msg = "🔐 <b>" . APP_NAME . " - 2FA Giriş Kodu</b>\n\nSayın <b>" . htmlspecialchars($user['full_name']) . "</b>,\nDepo yönetim paneline giriş için doğrulama kodunuz:\n\n👉 <code>{$code}</code>\n\nIP: {$ip}\nGeçerlilik süresi: 5 dakika.";
                        send_telegram_message($bot_token, $chat_id, $msg);

                        header('Location: verify-2fa.php');
                        exit;
                    }

                    login_user($user);
                    log_activity('login', 'Kullanıcı giriş yaptı: ' . $user['username']);
                    set_flash('success', 'Hoş geldiniz, ' . $user['full_name'] . '!');
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Geçersiz kullanıcı adı veya şifre.';
                }
            } catch (Exception $e) {
                $error = 'Veritabanı bağlantı hatası: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Giriş Yap';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <a href="#login-form" class="skip-link">Giriş formuna atla (Enter)</a>

    <div class="a11y-bar" role="region" aria-label="Erişilebilirlik Araçları">
        <span><?= APP_NAME ?> Güvenli Giriş</span>
        <div class="a11y-tools">
            <button type="button" id="font-dec-btn" class="a11y-btn" aria-label="Yazı boyutunu küçült">A-</button>
            <button type="button" id="font-reset-btn" class="a11y-btn" aria-label="Yazı boyutunu sıfırla">A</button>
            <button type="button" id="font-inc-btn" class="a11y-btn" aria-label="Yazı boyutunu büyüt">A+</button>
            <button type="button" id="contrast-toggle-btn" class="a11y-btn" aria-pressed="false">
                <span aria-hidden="true">◐</span> Yüksek Kontrast
            </button>
            <button type="button" id="theme-toggle-btn" class="a11y-btn" aria-pressed="false">
                <span aria-hidden="true">🌓</span> Tema
            </button>
        </div>
    </div>

    <div class="auth-wrapper">
        <main id="login-form" class="auth-card" tabindex="-1">
            <div class="auth-logo">
                <div class="brand-icon" style="margin: 0 auto; width: 60px; height: 60px; font-size: 2rem;" aria-hidden="true">📦</div>
                <h1><?= APP_NAME ?></h1>
                <p class="text-muted">Erişilebilir Depo ve Stok Yönetimi</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive">
                    <span aria-hidden="true">⚠</span>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username" class="form-label">
                        Kullanıcı Adı veya E-Posta <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?= e($_POST['username'] ?? '') ?>" 
                           required autofocus autocomplete="username"
                           aria-required="true"
                           placeholder="admin">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        Şifre <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           required autocomplete="current-password"
                           aria-required="true"
                           placeholder="••••••••">
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Giriş Yap
                    </button>
                </div>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border); font-size: 0.8125rem; color: var(--color-text-muted);">
                <strong>Varsayılan Demo Giriş Bilgileri:</strong><br>
                Kullanıcı: <code>admin</code> / Şifre: <code>admin123</code><br>
                Depocu: <code>depocu</code> / Şifre: <code>admin123</code>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
