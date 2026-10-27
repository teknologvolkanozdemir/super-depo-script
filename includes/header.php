<?php
/**
 * Süper Depo Scripti - Standart Erişilebilir Başlık (Header)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Kullanıcı oturumu gerektir (login.php hariç)
$current_script = basename($_SERVER['PHP_SELF']);
if ($current_script !== 'login.php' && $current_script !== 'install.php') {
    require_login();
}

$user = current_user();
$active_page = $active_page ?? '';
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Süper Depo - Erişilebilir, modern PHP ve MySQL tabanlı Depo ve Stok Takip Yazılımı">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    
    <!-- Ana Stil Dosyası -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Ekran Okuyucu ve Klavye Kullanıcıları İçin Atlama Bağlantısı -->
    <a href="#main-content" class="skip-link">Ana İçeriğe Doğrudan Atla (Enter)</a>

    <!-- Üst Erişilebilirlik ve Tercihler Çubuğu -->
    <div class="a11y-bar no-print" role="region" aria-label="Erişilebilirlik Araçları">
        <div class="shortcuts-badge">
            <span class="sr-only">Klavye Kısayolları: </span>
            <small>Kısayollar: <kbd>Alt+N</kbd> Yeni Ürün | <kbd>Alt+G</kbd> Giriş | <kbd>Alt+C</kbd> Çıkış | <kbd>Alt+S</kbd> Arama</small>
        </div>
        <div class="a11y-tools">
            <span id="font-size-label" class="sr-only">Yazı Boyutu Ayarları</span>
            <button type="button" id="font-dec-btn" class="a11y-btn" aria-label="Yazı boyutunu küçült" title="Yazı boyutunu küçült">A-</button>
            <button type="button" id="font-reset-btn" class="a11y-btn" aria-label="Yazı boyutunu sıfırla" title="Yazı boyutunu sıfırla">A</button>
            <button type="button" id="font-inc-btn" class="a11y-btn" aria-label="Yazı boyutunu büyüt" title="Yazı boyutunu büyüt">A+</button>
            
            <button type="button" id="contrast-toggle-btn" class="a11y-btn" aria-pressed="false" title="Yüksek kontrast modunu aç/kapat">
                <span aria-hidden="true">◐</span> Yüksek Kontrast
            </button>
            
            <button type="button" id="theme-toggle-btn" class="a11y-btn" aria-pressed="false" title="Aydınlık / Karanlık tema değiştir">
                <span aria-hidden="true">🌓</span> Tema
            </button>
        </div>
    </div>

    <?php if ($user): ?>
    <!-- Ana Üst Başlık ve Menü -->
    <header class="site-header no-print">
        <div class="header-container">
            <a href="index.php" class="brand" aria-label="Süper Depo Yönetimi Ana Sayfa">
                <div class="brand-icon" aria-hidden="true">📦</div>
                <div class="brand-text">
                    <h1><?= APP_NAME ?></h1>
                    <span>Depo & Stok Yönetim Sistemi</span>
                </div>
            </a>

            <nav class="main-nav" aria-label="Ana Menü">
                <ul class="nav-list" role="menubar">
                    <li role="none">
                        <a href="index.php" class="nav-link <?= $active_page === 'dashboard' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'dashboard' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📊</span> Kontrol Paneli
                        </a>
                    </li>
                    <li role="none">
                        <a href="products.php" class="nav-link <?= $active_page === 'products' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'products' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📦</span> Ürünler
                        </a>
                    </li>
                    <li role="none">
                        <a href="stock-in.php" class="nav-link <?= $active_page === 'stock-in' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'stock-in' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📥</span> Ürün Girişi
                        </a>
                    </li>
                    <li role="none">
                        <a href="stock-out.php" class="nav-link <?= $active_page === 'stock-out' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'stock-out' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📤</span> Ürün Çıkışı
                        </a>
                    </li>
                    <li role="none">
                        <a href="stock-movements.php" class="nav-link <?= $active_page === 'movements' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'movements' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📜</span> Hareketler
                        </a>
                    </li>
                    <li role="none">
                        <a href="stock-adjustment.php" class="nav-link <?= $active_page === 'adjustment' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'adjustment' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">⚖️</span> Sayım & Transfer
                        </a>
                    </li>
                    <li role="none">
                        <a href="reports.php" class="nav-link <?= $active_page === 'reports' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'reports' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📈</span> Raporlar
                        </a>
                    </li>
                    <li role="none">
                        <a href="categories.php" class="nav-link <?= $active_page === 'categories' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'categories' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">🏷️</span> Kategoriler
                        </a>
                    </li>
                    <li role="none">
                        <a href="suppliers.php" class="nav-link <?= $active_page === 'suppliers' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'suppliers' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">🚚</span> Tedarikçiler
                        </a>
                    </li>
                    <li role="none">
                        <a href="customers.php" class="nav-link <?= $active_page === 'customers' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'customers' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">🏢</span> Müşteriler
                        </a>
                    </li>
                    <li role="none">
                        <a href="locations.php" class="nav-link <?= $active_page === 'locations' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'locations' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">📍</span> Konumlar
                        </a>
                    </li>
                    <li role="none">
                        <a href="woocommerce-sync.php" class="nav-link <?= $active_page === 'woocommerce' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'woocommerce' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">🛒</span> WooCommerce
                        </a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                    <li role="none">
                        <a href="users.php" class="nav-link <?= $active_page === 'users' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'users' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">👥</span> Kullanıcılar
                        </a>
                    </li>
                    <li role="none">
                        <a href="settings.php" class="nav-link <?= $active_page === 'settings' ? 'active' : '' ?>" role="menuitem" <?= $active_page === 'settings' ? 'aria-current="page"' : '' ?>>
                            <span aria-hidden="true">⚙️</span> Ayarlar
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?= e($user['full_name']) ?></div>
                    <div class="user-role"><?= e($user['role'] === 'admin' ? 'Yönetici' : ($user['role'] === 'manager' ? 'Depo Amiri' : 'Personel')) ?></div>
                </div>
                <a href="profile.php" class="btn btn-sm btn-outline" title="Profil Ayarları">Profil</a>
                <a href="logout.php" class="btn btn-sm btn-danger" title="Oturumu Güvenli Kapat">Çıkış</a>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <!-- Ana İçerik Bölgesi -->
    <main id="main-content" class="main-content" tabindex="-1">
        <!-- Flash Bildirimleri -->
        <?php $flashes = get_flashes(); ?>
        <?php if (!empty($flashes)): ?>
            <div class="flashes-container" role="region" aria-label="Sistem Bildirimleri">
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                        <span class="alert-icon" aria-hidden="true">
                            <?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'danger' ? '⚠' : 'ℹ') ?>
                        </span>
                        <div><?= e($flash['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
