<?php
/**
 * Oturumu Kapatma
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    log_activity('logout', 'Kullanıcı çıkış yaptı.');
    logout_user();
}

header('Location: login.php');
exit;
