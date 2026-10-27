<?php
/**
 * Süper Depo Scripti - Genel Yapılandırma Dosyası
 * PHP ve MySQL Tabanlı, Erişilebilir Depo Yönetim Sistemi
 */

// Oturum başlatma (aktif değilse)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Zaman dilimi ve hata ayarları
date_default_timezone_set('Europe/Istanbul');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Canlıda 0, geliştirme için 1 yapılabilir

// Uygulama Sabitleri
define('APP_NAME', 'Süper Depo');
define('APP_VERSION', '1.0.0');
define('APP_URL', ''); // Boş bırakılırsa dinamik algılanır

// Veritabanı Sürücüsü: 'mysql' veya 'sqlite'
// Varsayılan olarak MySQL hedeflenir, pdo_mysql yoksa veya bağlantı kurulamazsa 
// SQLite'a zarifçe (graceful fallback) geçerek anında çalışmasını sağlar.
define('DB_DRIVER', getenv('DB_DRIVER') ?: (extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite'));

// MySQL Yapılandırması
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'super_depo');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// SQLite Dosya Yolu (Taşınabilir / Hızlı Test)
define('SQLITE_PATH', __DIR__ . '/data/depo.sqlite');

// Para Birimi ve Dil
define('DEFAULT_CURRENCY', '₺');
define('DEFAULT_CURRENCY_CODE', 'TRY');

// Sayfalama Limiti
define('ITEMS_PER_PAGE', 15);
