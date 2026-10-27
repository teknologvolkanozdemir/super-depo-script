<?php
/**
 * Süper Depo Scripti - Kurulum ve Veritabanı Hazırlama Sihirbazı
 * Web arayüzü ve CLI üzerinden çalıştırılabilir
 */

$is_cli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';

function run_installation(?string $driver = null, ?string $admin_pass = null): array {
    try {
        $driver = $driver ?: DB_DRIVER;
        
        if ($driver === 'mysql' && extension_loaded('pdo_mysql')) {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `" . DB_NAME . "`;");

            // database.sql çalıştır
            $sql = file_get_contents(__DIR__ . '/database.sql');
            $pdo->exec($sql);
            return ['success' => true, 'driver' => 'MySQL', 'db' => DB_NAME];
        } else {
            // SQLite kurulumu
            $db = get_db();
            init_sqlite_schema($db);
            return ['success' => true, 'driver' => 'SQLite', 'db' => SQLITE_PATH];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// CLI Çalıştırma Desteği
if ($is_cli) {
    echo "========================================\n";
    echo "   Süper Depo Scripti - Kurulum Sihirbazı\n";
    echo "========================================\n";
    $res = run_installation();
    if ($res['success']) {
        echo "Kurulum başarıyla tamamlandı!\n";
        echo "Veritabanı: " . $res['driver'] . " (" . $res['db'] . ")\n";
        echo "Varsayılan Yönetici: admin / admin123\n";
        exit(0);
    } else {
        echo "Kurulum hatası: " . $res['error'] . "\n";
        exit(1);
    }
}

// Web İstekleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = run_installation();
    if ($res['success']) {
        $message = "Kurulum başarıyla tamamlandı! Sürücü: " . $res['driver'];
    } else {
        $error = "Kurulum sırasında hata: " . $res['error'];
    }
}

$page_title = 'Sistem Kurulumu';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <main class="auth-card" tabindex="-1">
            <div class="auth-logo">
                <div class="brand-icon" style="margin: 0 auto; width: 60px; height: 60px; font-size: 2rem;" aria-hidden="true">🛠️</div>
                <h1><?= APP_NAME ?> Kurulum</h1>
                <p class="text-muted">Veritabanı tablolarını ve ilk verileri hazırlayın</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert">
                    <div><?= e($message) ?></div>
                </div>
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="login.php" class="btn btn-primary" style="width: 100%;">Giriş Yap</a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <p style="margin-bottom: 1.5rem; font-size: 0.9375rem; color: var(--color-text-muted);">
                    Bu sihirbaz, depo veritabanını, ürün tablolarını, kullanıcıları ve örnek hareketleri otomatik olarak kuracaktır.
                </p>

                <div style="background: var(--color-surface-hover); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
                    <strong>Algılanan Yapılandırma:</strong><br>
                    • Sürücü: <code><?= strtoupper(DB_DRIVER) ?></code><br>
                    • Veritabanı: <code><?= DB_NAME ?></code><br>
                    • PHP Sürümü: <code><?= PHP_VERSION ?></code>
                </div>

                <form action="install.php" method="POST">
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; padding: 0.75rem;">
                        Kurulumu Başlat
                    </button>
                </form>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
