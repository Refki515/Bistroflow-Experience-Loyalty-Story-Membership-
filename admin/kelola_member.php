<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';
$search = trim($_GET['cari'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterTier = $_GET['tier'] ?? '';

// ============================================================
// PROSES EDIT MEMBER (status akun, role, tier)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_member') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $statusAkun = $_POST['status_akun'] ?? '';
    $role = $_POST['role'] ?? '';
    $tier = $_POST['tier_member'] ?? '';

    if (!in_array($statusAkun, ['aktif', 'nonaktif'], true)) {
        $errors[] = 'Status akun tidak dikenali.';
    }
    if (!in_array($role, ['member', 'admin', 'chef', 'produsen'], true)) {
        $errors[] = 'Role pengguna tidak dikenali sistem.';
    }
    if (!in_array($tier, ['bronze', 'silver', 'gold'], true)) {
        $errors[] = 'Tier keanggotaan tidak valid.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET status_akun = ?, role = ?, tier_member = ? WHERE user_id = ?");
        $stmt->execute([$statusAkun, $role, $tier, $targetId]);
        $success = 'Data pengguna berhasil diperbarui.';
    }
}

$sql = "SELECT u.*, COALESCE((SELECT saldo_credits FROM credits c WHERE c.user_id = u.user_id ORDER BY credit_id DESC LIMIT 1), 0) AS saldo
        FROM users u WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.nama_lengkap LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filterRole !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $filterRole;
}
if ($filterTier !== '') {
    $sql .= " AND u.tier_member = ?";
    $params[] = $filterTier;
}
$sql .= " ORDER BY u.tanggal_daftar DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Kelola Member';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Kelola Member &amp; Pengguna</h2>
        <p>Kelola akun, role, dan tier seluruh pengguna sistem.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="GET" class="flex gap-sm mb-md" style="flex-wrap:wrap;">
    <input type="text" name="cari" class="form-control" placeholder="Cari nama atau email..." value="<?= e($search) ?>" style="flex:1; min-width:200px;">
    <select name="role" class="form-control" style="width:160px;">
        <option value="">Semua Role</option>
        <option value="member" <?= $filterRole === 'member' ? 'selected' : '' ?>>Member</option>
        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="chef" <?= $filterRole === 'chef' ? 'selected' : '' ?>>Chef</option>
        <option value="produsen" <?= $filterRole === 'produsen' ? 'selected' : '' ?>>Produsen</option>
    </select>
    <select name="tier" class="form-control" style="width:160px;">
        <option value="">Semua Tier</option>
        <option value="bronze" <?= $filterTier === 'bronze' ? 'selected' : '' ?>>Bronze</option>
        <option value="silver" <?= $filterTier === 'silver' ? 'selected' : '' ?>>Silver</option>
        <option value="gold" <?= $filterTier === 'gold' ? 'selected' : '' ?>>Gold</option>
    </select>
    <button type="submit" class="btn btn-secondary"><span class="material-symbols-outlined" style="font-size:18px;">search</span></button>
</form>

<?php if (empty($users)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">person_off</span><h4>Tidak ada pengguna ditemukan</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Nama</th><th>Email</th><th>Role</th><th>Tier</th><th>Saldo Credits</th><th>Status</th><th>Tgl Daftar</th><th class="text-right">Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['nama_lengkap']) ?></td>
                <td class="text-muted"><?= e($u['email']) ?></td>
                <td><span class="badge badge-neutral"><?= e(ucfirst($u['role'])) ?></span></td>
                <td><span class="badge badge-tier-<?= e($u['tier_member']) ?>"><?= e(ucfirst($u['tier_member'])) ?></span></td>
                <td><?= formatCredits((int)$u['saldo']) ?></td>
                <td><span class="badge <?= $u['status_akun'] === 'aktif' ? 'badge-success' : 'badge-error' ?>"><?= e(ucfirst($u['status_akun'])) ?></span></td>
                <td class="text-muted"><?= date('d M Y', strtotime($u['tanggal_daftar'])) ?></td>
                <td class="text-right">
                    <button class="btn btn-outline" style="padding:6px 14px; font-size:12px;"
                        onclick='openEditModal(<?= $u['user_id'] ?>, <?= json_encode($u['nama_lengkap']) ?>, <?= json_encode($u['status_akun']) ?>, <?= json_encode($u['role']) ?>, <?= json_encode($u['tier_member']) ?>)'>
                        Edit
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal Edit Member -->
<div class="modal-overlay" id="edit-member-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Pengguna</h3>
            <button class="modal-close" onclick="closeModal('edit-member-modal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_member">
            <input type="hidden" name="user_id" id="edit-user-id">
            <div class="modal-body">
                <p style="margin-bottom: var(--space-md);">Nama: <strong id="edit-user-name" style="color: var(--color-primary);"></strong></p>

                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select class="form-control" name="role" id="edit-role" required>
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                        <option value="chef">Chef</option>
                        <option value="produsen">Produsen</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tier Keanggotaan <span class="required">*</span></label>
                    <select class="form-control" name="tier_member" id="edit-tier" required>
                        <option value="bronze">Bronze</option>
                        <option value="silver">Silver</option>
                        <option value="gold">Gold</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status Akun <span class="required">*</span></label>
                    <select class="form-control" name="status_akun" id="edit-status" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('edit-member-modal')">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, status, role, tier) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-name').textContent = name;
    document.getElementById('edit-status').value = status;
    document.getElementById('edit-role').value = role;
    document.getElementById('edit-tier').value = tier;
    openModal('edit-member-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
