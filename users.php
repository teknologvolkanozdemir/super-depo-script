<?php
/**
 * Süper Depo Scripti - Kullanıcı Yönetimi
 */

$page_title = 'Kullanıcılar';
$active_page = 'users';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

require_role('admin');
$db = get_db();
$errors = [];

// Kullanıcı Ekleme / Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errors[] = 'CSRF doğrulaması geçersiz.';
    } else {
        $action = $_POST['action'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'manager', 'staff']) ? $_POST['role'] : 'staff';
        $password = $_POST['password'] ?? '';

        if ($action === 'create') {
            if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
                $errors[] = 'Tüm alanları eksiksiz doldurunuz.';
            } else {
                // Benzersizlik kontrolü
                $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_in = $db->prepare('INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                    $stmt_in->execute([$username, $email, $hash, $full_name, $role]);
                    log_activity('user_create', "Yeni kullanıcı eklendi: {$username} ({$role})");
                    set_flash('success', "'{$full_name}' kullanıcısı başarıyla eklendi.");
                    header('Location: users.php');
                    exit;
                }
            }
        } elseif ($action === 'update_password') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id > 0 && !empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt_up = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt_up->execute([$hash, $user_id]);
                log_activity('password_reset', "Kullanıcı şifresi sıfırlandı ID: {$user_id}");
                set_flash('success', 'Şifre başarıyla güncellendi.');
                header('Location: users.php');
                exit;
            }
        } elseif ($action === 'toggle_status') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id > 0 && $user_id !== (int)$user['id']) {
                $stmt_tog = $db->prepare('UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?');
                $stmt_tog->execute([$user_id]);
                log_activity('user_status_toggle', "Kullanıcı durumu değiştirildi ID: {$user_id}");
                set_flash('success', 'Kullanıcı durumu güncellendi.');
                header('Location: users.php');
                exit;
            }
        }
    }
}

$users_list = $db->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Kullanıcı Yönetimi</h1>
        <p class="page-subtitle">Depo yöneticileri, amirleri ve çalışanlarının yetkileri</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Yeni Kullanıcı Formu -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Yeni Kullanıcı Oluştur</h2>
        </div>
        <form action="users.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="username" class="form-label">Kullanıcı Adı <span class="form-required">*</span></label>
                <input type="text" id="username" name="username" class="form-control" required placeholder="ornek_kullanici">
            </div>

            <div class="form-group">
                <label for="full_name" class="form-label">Ad Soyad <span class="form-required">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="Ali Veli">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">E-Posta <span class="form-required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="ali@ornek.com">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">İlk Giriş Şifresi <span class="form-required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Yetki Rolü:</label>
                <select id="role" name="role" class="form-control">
                    <option value="staff">Depo Personeli (Giriş/Çıkış yapabilir)</option>
                    <option value="manager">Depo Amiri (Ürün/Kategori yönetebilir)</option>
                    <option value="admin">Sistem Yöneticisi (Tam Yetki)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Kullanıcıyı Kaydet</button>
        </form>
    </div>

    <!-- Kullanıcılar Tablosu -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Kayıtlı Kullanıcılar (<?= count($users_list) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table" aria-label="Kullanıcı Listesi">
                <thead>
                    <tr>
                        <th scope="col">Ad Soyad</th>
                        <th scope="col">Kullanıcı Adı</th>
                        <th scope="col">E-Posta</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Durum</th>
                        <th scope="col" style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td><strong><?= e($u['full_name']) ?></strong></td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-danger">Yönetici</span>
                            <?php elseif ($u['role'] === 'manager'): ?>
                                <span class="badge badge-warning">Depo Amiri</span>
                            <?php else: ?>
                                <span class="badge badge-info">Personel</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $u['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Pasif</span>' ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ($u['id'] !== $user['id']): ?>
                                <form action="users.php" method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline">
                                        <?= $u['is_active'] ? 'Pasife Al' : 'Aktif Et' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
