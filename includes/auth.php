<?php
/**
 * Oturum Yönetimi, Yetkilendirme ve Güvenlik
 */

require_once __DIR__ . '/../config.php';

// Oturum kontrolü
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Mevcut kullanıcı bilgileri
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff',
        'email' => $_SESSION['email'] ?? ''
    ];
}

// Giriş zorunluluğu
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Lütfen devam etmek için giriş yapınız.');
        header('Location: login.php');
        exit;
    }
}

// Rol kontrolü (örn. admin, manager)
function require_role(array|string $roles): void {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        set_flash('danger', 'Bu işlem için yetkiniz bulunmamaktadır.');
        header('Location: index.php');
        exit;
    }
}

// Kullanıcı girişi
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
}

// Kullanıcı çıkışı
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// CSRF Belirteci
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Formlar için CSRF gizli girdi alanı
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// CSRF Doğrulama
function verify_csrf(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Bildirim Mesajları (Flash)
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}
