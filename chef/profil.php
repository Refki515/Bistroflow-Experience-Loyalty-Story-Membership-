<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'chef', 'produsen', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profil') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $telepon = trim($_POST['nomor_telepon'] ?? '');

    if (strlen($nama) < 3) {
        $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter.';
    }
    if (!preg_match('/^08[0-9]{8,11}$/', $telepon)) {
        $errors[] = 'Nomor telepon tidak valid.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, nomor_telepon = ? WHERE user_id = ?");
        $stmt->execute([$nama, $telepon, $userId]);
        $_SESSION['nama_lengkap'] = $nama;
        $success = 'Profil berhasil diperbarui.';
        $user['nama_lengkap'] = $nama;
        $user['nomor_telepon'] = $telepon;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ganti_password') {
    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (!password_verify($passwordLama, $user['password'])) {
        $errors[] = 'Password lama tidak sesuai.';
    } elseif (strlen($passwordBaru) < 8) {
        $errors[] = 'Password baru minimal 8 karakter.';
    } elseif ($passwordBaru !== $konfirmasi) {
        $errors[] = 'Konfirmasi password tidak sama.';
    } else {
        $hash = password_hash($passwordBaru, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hash, $userId]);
        $success = 'Password berhasil diperbarui.';
    }
}

// Aktivitas terakhir
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservasi WHERE user_id_member = ?");
$stmt->execute([$userId]);
$totalReservasi = (int)$stmt->fetchColumn();

$saldo = getSaldoCredits($userId);

$pageTitle = 'Profil Saya';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Profil Saya</h2>
        <p>Kelola informasi akun dan keamanan login Anda.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="stat-grid" style="grid-template-columns: repeat(3,1fr);">
    <div class="stat-card">
        <span class="label">Tier Keanggotaan</span>
        <div class="value" style="font-size:24px;"><?= e(ucfirst($user['tier_member'])) ?></div>
    </div>
    <div class="stat-card">
        <span class="label">Saldo Credits</span>
        <div class="value"><?= formatCredits($saldo) ?> pts</div>
    </div>
    <div class="stat-card">
        <span class="label">Total Reservasi</span>
        <div class="value light"><?= $totalReservasi ?></div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: 1fr 1fr; align-items: start;">
    <!-- Form Edit Profil -->
    <div class="card">
        <h3 class="section-title">Edit Informasi Akun</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_profil">
            <div class="form-group">
                <label>Email (tidak dapat diubah)</label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap <span class="required">*</span></label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= e($user['nama_lengkap']) ?>" required minlength="3">
            </div>
            <div class="form-group">
                <label for="nomor_telepon">Nomor Telepon <span class="required">*</span></label>
                <input type="tel" class="form-control" id="nomor_telepon" name="nomor_telepon" value="<?= e($user['nomor_telepon']) ?>" required pattern="^08[0-9]{8,11}$">
                <p class="form-help">Diawali 08, angka saja</p>
            </div>
            <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan Perubahan</span></button>
        </form>
    </div>

    <!-- Form Ganti Password -->
    <div class="card">
        <h3 class="section-title">Ubah Password</h3>
        <form method="POST">
            <input type="hidden" name="action" value="ganti_password">
            <div class="form-group">
                <label for="password_lama">Password Lama <span class="required">*</span></label>
                <input type="password" class="form-control" id="password_lama" name="password_lama" required>
            </div>
            <div class="form-group">
                <label for="password_baru">Password Baru <span class="required">*</span></label>
                <input type="password" class="form-control" id="password_baru" name="password_baru" required minlength="8">
                <p class="form-help">Minimal 8 karakter</p>
            </div>
            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password Baru <span class="required">*</span></label>
                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary"><span class="btn-label">Ubah Password</span></button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
