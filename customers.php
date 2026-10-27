<?php
/**
 * Süper Depo Scripti - Müşteri Yönetimi
 */

$page_title = 'Müşteriler';
$active_page = 'customers';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

// Ekleme / Düzenleme / Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errors[] = 'CSRF geçersiz.';
    } else {
        $action = $_POST['action'];
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tax = trim($_POST['tax_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($action === 'create') {
            if (empty($name)) {
                $errors[] = 'Müşteri / Firma adı zorunludur.';
            } else {
                $stmt = $db->prepare('INSERT INTO customers (name, contact_person, phone, email, tax_number, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $contact, $phone, $email, $tax, $address, $notes]);
                log_activity('customer_create', "Müşteri eklendi: {$name}");
                set_flash('success', "'{$name}' müşterisi eklendi.");
                header('Location: customers.php');
                exit;
            }
        } elseif ($action === 'update') {
            $cust_id = (int)($_POST['customer_id'] ?? 0);
            if ($cust_id <= 0 || empty($name)) {
                $errors[] = 'Müşteri adı zorunludur.';
            } else {
                $stmt = $db->prepare('UPDATE customers SET name = ?, contact_person = ?, phone = ?, email = ?, tax_number = ?, address = ?, notes = ? WHERE id = ?');
                $stmt->execute([$name, $contact, $phone, $email, $tax, $address, $notes, $cust_id]);
                log_activity('customer_update', "Müşteri güncellendi: {$name}");
                set_flash('success', "'{$name}' güncellendi.");
                header('Location: customers.php');
                exit;
            }
        } elseif ($action === 'delete') {
            require_role(['admin', 'manager']);
            $cust_id = (int)($_POST['customer_id'] ?? 0);
            $db->prepare('UPDATE customers SET is_active = 0 WHERE id = ?')->execute([$cust_id]);
            log_activity('customer_delete', "Müşteri silindi ID: {$cust_id}");
            set_flash('success', 'Müşteri arşive alındı.');
            header('Location: customers.php');
            exit;
        }
    }
}

$customers = $db->query('SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

$edit_cust = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM customers WHERE id = ? AND is_active = 1');
    $stmt->execute([$edit_id]);
    $edit_cust = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Müşteriler & Alıcılar</h1>
        <p class="page-subtitle">Ürün sevkiyatı yapılan alıcı firma ve müşteri kayıtları</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $edit_cust ? 'Müşteriyi Düzenle' : 'Yeni Müşteri Ekle' ?></h2>
        </div>
        <form action="customers.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_cust ? 'update' : 'create' ?>">
            <?php if ($edit_cust): ?>
                <input type="hidden" name="customer_id" value="<?= (int)$edit_cust['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Müşteri / Şirket Adı <span class="form-required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($edit_cust['name'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Yetkili / İlgili Kişi:</label>
                <input type="text" id="contact_person" name="contact_person" class="form-control" value="<?= e($edit_cust['contact_person'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="form-label">Telefon:</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($edit_cust['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">E-Posta:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($edit_cust['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="tax_number" class="form-label">Vergi Numarası / TCKN:</label>
                <input type="text" id="tax_number" name="tax_number" class="form-control" value="<?= e($edit_cust['tax_number'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Teslimat / Fatura Adresi:</label>
                <textarea id="address" name="address" class="form-control" rows="2"><?= e($edit_cust['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Müşteri Notları:</label>
                <textarea id="notes" name="notes" class="form-control" rows="2"><?= e($edit_cust['notes'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_cust ? 'Güncelle' : 'Müşteriyi Kaydet' ?>
                </button>
                <?php if ($edit_cust): ?>
                    <a href="customers.php" class="btn btn-outline">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Kayıtlı Müşteriler (<?= count($customers) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table" aria-label="Müşteri Listesi">
                <thead>
                    <tr>
                        <th scope="col">Müşteri Adı</th>
                        <th scope="col">Yetkili</th>
                        <th scope="col">İletişim</th>
                        <th scope="col">Vergi No</th>
                        <th scope="col" style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <strong><?= e($c['name']) ?></strong>
                            <?php if ($c['address']): ?>
                                <small class="text-muted" style="display: block;"><?= e($c['address']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($c['contact_person'] ?: '-') ?></td>
                        <td>
                            <?= e($c['phone'] ?: '') ?>
                            <?php if ($c['email']): ?>
                                <br><small class="text-muted"><?= e($c['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($c['tax_number'] ?: '-') ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="customers.php?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline">Düzenle</a>
                            <form action="customers.php" method="POST" style="display: inline;" onsubmit="return confirm('Silmek istediğinizden emin misiniz?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
