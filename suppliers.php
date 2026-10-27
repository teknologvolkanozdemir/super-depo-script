<?php
/**
 * Süper Depo Scripti - Tedarikçi Yönetimi
 */

$page_title = 'Tedarikçiler';
$active_page = 'suppliers';

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
                $errors[] = 'Tedarikçi adı zorunludur.';
            } else {
                $stmt = $db->prepare('INSERT INTO suppliers (name, contact_person, phone, email, tax_number, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $contact, $phone, $email, $tax, $address, $notes]);
                log_activity('supplier_create', "Tedarikçi eklendi: {$name}");
                set_flash('success', "'{$name}' tedarikçisi eklendi.");
                header('Location: suppliers.php');
                exit;
            }
        } elseif ($action === 'update') {
            $sup_id = (int)($_POST['supplier_id'] ?? 0);
            if ($sup_id <= 0 || empty($name)) {
                $errors[] = 'Tedarikçi adı zorunludur.';
            } else {
                $stmt = $db->prepare('UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, tax_number = ?, address = ?, notes = ? WHERE id = ?');
                $stmt->execute([$name, $contact, $phone, $email, $tax, $address, $notes, $sup_id]);
                log_activity('supplier_update', "Tedarikçi güncellendi: {$name}");
                set_flash('success', "'{$name}' güncellendi.");
                header('Location: suppliers.php');
                exit;
            }
        } elseif ($action === 'delete') {
            require_role(['admin', 'manager']);
            $sup_id = (int)($_POST['supplier_id'] ?? 0);
            $db->prepare('UPDATE suppliers SET is_active = 0 WHERE id = ?')->execute([$sup_id]);
            log_activity('supplier_delete', "Tedarikçi silindi ID: {$sup_id}");
            set_flash('success', 'Tedarikçi arşive alındı.');
            header('Location: suppliers.php');
            exit;
        }
    }
}

$suppliers = $db->query('SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

$edit_sup = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE id = ? AND is_active = 1');
    $stmt->execute([$edit_id]);
    $edit_sup = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tedarikçi Firmalar</h1>
        <p class="page-subtitle">Mal aldığınız tedarikçi ve distribütör bilgileri</p>
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
            <h2 class="card-title"><?= $edit_sup ? 'Tedarikçiyi Düzenle' : 'Yeni Tedarikçi Ekle' ?></h2>
        </div>
        <form action="suppliers.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_sup ? 'update' : 'create' ?>">
            <?php if ($edit_sup): ?>
                <input type="hidden" name="supplier_id" value="<?= (int)$edit_sup['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Firma / Tedarikçi Adı <span class="form-required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($edit_sup['name'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Yetkili Kişi:</label>
                <input type="text" id="contact_person" name="contact_person" class="form-control" value="<?= e($edit_sup['contact_person'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="form-label">Telefon:</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($edit_sup['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">E-Posta:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($edit_sup['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="tax_number" class="form-label">Vergi No / Dairesi:</label>
                <input type="text" id="tax_number" name="tax_number" class="form-control" value="<?= e($edit_sup['tax_number'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Adres:</label>
                <textarea id="address" name="address" class="form-control" rows="2"><?= e($edit_sup['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notlar:</label>
                <textarea id="notes" name="notes" class="form-control" rows="2"><?= e($edit_sup['notes'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_sup ? 'Güncelle' : 'Tedarikçiyi Kaydet' ?>
                </button>
                <?php if ($edit_sup): ?>
                    <a href="suppliers.php" class="btn btn-outline">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Kayıtlı Tedarikçiler (<?= count($suppliers) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table" aria-label="Tedarikçi Listesi">
                <thead>
                    <tr>
                        <th scope="col">Firma Adı</th>
                        <th scope="col">Yetkili</th>
                        <th scope="col">İletişim</th>
                        <th scope="col">Vergi No</th>
                        <th scope="col" style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['name']) ?></strong>
                            <?php if ($s['address']): ?>
                                <small class="text-muted" style="display: block;"><?= e($s['address']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($s['contact_person'] ?: '-') ?></td>
                        <td>
                            <?= e($s['phone'] ?: '') ?>
                            <?php if ($s['email']): ?>
                                <br><small class="text-muted"><?= e($s['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($s['tax_number'] ?: '-') ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="suppliers.php?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline">Düzenle</a>
                            <form action="suppliers.php" method="POST" style="display: inline;" onsubmit="return confirm('Silmek istediğinizden emin misiniz?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
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
