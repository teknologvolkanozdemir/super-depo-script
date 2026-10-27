<?php
/**
 * Süper Depo Scripti - Kullanıcı Profili ve Şifre Değiştirme
 */

$page_title = 'Profilim';
$active_page = 'profile';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$user_data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'CSRF geçersiz.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($full_name) || empty($email)) {
                $errors[] = 'Ad soyad ve e-posta alanları zorunludur.';
            } else {
                $stmt_up = $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
                $stmt_up->execute([$full_name, $email, $user['id']]);
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                log_activity('profile_update', 'Profil bilgileri güncellendi.');
                set_flash('success', 'Profil bilgileriniz güncellendi.');
                header('Location: profile.php');
                exit;
            }
        } elseif ($action === 'change_password') {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (!password_verify($current_pass, $user_data['password_hash'])) {
                $errors[] = 'Mevcut şifrenizi yanlış girdiniz.';
            } elseif (strlen($new_pass) < 6) {
                $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
            } elseif ($new_pass !== $confirm_pass) {
                $errors[] = 'Yeni şifreler eşleşmiyor.';
            } else {
                $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt_pw = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt_pw->execute([$new_hash, $user['id']]);
                log_activity('password_change', 'Kullanıcı kendi şifresini değiştirdi.');
                set_flash('success', 'Şifreniz başarıyla değiştirildi.');
                header('Location: profile.php');
                exit;
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Profil & Hesap Ayarları</h1>
        <p class="page-subtitle">Hesap bilgilerinizi güncelleyin ve şifrenizi değiştirin</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Profil Bilgileri -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Hesap Bilgileri</h2>
        </div>
        <form action="profile.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="username" class="form-label">Kullanıcı Adı:</label>
                <input type="text" id="username" class="form-control" value="<?= e($user_data['username']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="full_name" class="form-label">Ad Soyad:</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($user_data['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">E-Posta:</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($user_data['email']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Bilgileri Güncelle</button>
        </form>
    </div>

    <!-- Şifre Değiştir -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Şifre Değiştir</h2>
        </div>
        <form action="profile.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="current_password" class="form-label">Mevcut Şifre:</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">Yeni Şifre (En az 6 karakter):</label>
                <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Yeni Şifre Tekrarı:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
            </div>

            <button type="submit" class="btn btn-warning">Şifreyi Değiştir</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
