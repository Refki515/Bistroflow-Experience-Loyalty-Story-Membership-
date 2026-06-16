<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';

// ============================================================
// PROSES BUAT JADWAL WORKSHOP (Modul 5: Form Pembuatan Jadwal Workshop)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buat_workshop') {
    $judul = trim($_POST['judul_workshop'] ?? '');
    $deskripsi = trim($_POST['deskripsi_workshop'] ?? '');
    $chefId = (int)($_POST['chef_pengampu_id'] ?? 0);
    $tanggal = $_POST['tanggal_workshop'] ?? '';
    $waktu = $_POST['waktu_mulai'] ?? '';
    $kapasitas = (int)($_POST['kapasitas_peserta'] ?? 0);
    $biayaCredits = (int)($_POST['biaya_credits'] ?? 0);
    $hargaUang = (float)($_POST['harga_uang'] ?? 0);
    $metodeBayar = $_POST['metode_bayar_workshop'] ?? '';

    if (strlen($judul) < 5) $errors[] = 'Judul workshop wajib diisi minimal 5 karakter.';
    if (strlen($deskripsi) < 20) $errors[] = 'Deskripsi workshop wajib diisi minimal 20 karakter.';
    if ($chefId <= 0) $errors[] = 'Chef pengampu wajib dipilih.';
    if ($tanggal === '' || strtotime($tanggal) < strtotime('today')) $errors[] = 'Tanggal workshop tidak valid atau sudah lewat.';
    if ($waktu === '') $errors[] = 'Waktu mulai workshop wajib diisi.';
    if ($kapasitas < 1) $errors[] = 'Kapasitas peserta minimal 1 orang.';
    if (!in_array($metodeBayar, ['uang', 'credits', 'keduanya'], true)) $errors[] = 'Metode pembayaran tidak dikenali.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO workshops (judul_workshop, deskripsi_workshop, chef_pengampu_id, tanggal_workshop, waktu_mulai, kapasitas_peserta, biaya_credits, harga_uang, metode_bayar_workshop, status_workshop)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')"
        );
        $stmt->execute([$judul, $deskripsi, $chefId, $tanggal, $waktu, $kapasitas, $biayaCredits, $hargaUang, $metodeBayar]);

        // Notifikasi ke semua Member
        $stmt2 = $pdo->query("SELECT user_id, tier_member FROM users WHERE role = 'member'");
        foreach ($stmt2->fetchAll() as $member) {
            createNotification((int)$member['user_id'], 'workshop_baru', "Workshop baru: $judul telah dijadwalkan.", $member['tier_member']);
        }

        $success = 'Jadwal workshop berhasil dibuat.';
    }
}

// ============================================================
// PROSES KONFIRMASI KEHADIRAN (Modul 5/7: Konfirmasi Kehadiran & Generate Sertifikat)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'konfirmasi_kehadiran') {
    $pendaftaranId = (int)($_POST['pendaftaran_id'] ?? 0);
    $status = $_POST['status_kehadiran'] ?? '';

    if (!in_array($status, ['belum', 'hadir', 'tidak_hadir'], true)) {
        $errors[] = 'Status kehadiran tidak dikenali.';
    } else {
        $sertifikatPath = null;
        if ($status === 'hadir') {
            // Path sertifikat ditempatkan di direktori server (file PDF dibuat di luar konteks ini)
            $sertifikatPath = UPLOAD_URL_SERTIFIKAT . 'sertifikat-' . $pendaftaranId . '.pdf';
        }

        $stmt = $pdo->prepare("UPDATE pendaftaran_workshop SET status_kehadiran = ?, path_sertifikat = ? WHERE pendaftaran_id = ?");
        $stmt->execute([$status, $sertifikatPath, $pendaftaranId]);

        if ($status === 'hadir') {
            $stmt = $pdo->prepare("SELECT user_id_member, workshop_id FROM pendaftaran_workshop WHERE pendaftaran_id = ?");
            $stmt->execute([$pendaftaranId]);
            $row = $stmt->fetch();
            if ($row) {
                createNotification((int)$row['user_id_member'], 'sertifikat_tersedia', 'Sertifikat workshop Anda telah tersedia untuk diunduh.', null);
            }
        }
        $success = 'Status kehadiran berhasil diperbarui.';
    }
}

// Daftar workshop
$stmt = $pdo->query(
    "SELECT w.*, u.nama_lengkap AS chef_nama,
            (SELECT COUNT(*) FROM pendaftaran_workshop p WHERE p.workshop_id = w.workshop_id) AS terdaftar
     FROM workshops w JOIN users u ON u.user_id = w.chef_pengampu_id
     ORDER BY w.tanggal_workshop DESC"
);
$workshops = $stmt->fetchAll();

// Daftar Chef untuk dropdown
$stmt = $pdo->query("SELECT user_id, nama_lengkap FROM users WHERE role = 'chef' AND status_akun = 'aktif' ORDER BY nama_lengkap ASC");
$chefList = $stmt->fetchAll();

// Detail peserta jika workshop dipilih
$selectedWorkshopId = (int)($_GET['workshop_id'] ?? 0);
$peserta = [];
if ($selectedWorkshopId > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nama_lengkap, u.tier_member FROM pendaftaran_workshop p
         JOIN users u ON u.user_id = p.user_id_member WHERE p.workshop_id = ?"
    );
    $stmt->execute([$selectedWorkshopId]);
    $peserta = $stmt->fetchAll();
}

$pageTitle = 'Kelola Workshop';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Kelola Culinary Storytelling Workshops</h2>
        <p>Buat jadwal workshop baru dan kelola konfirmasi kehadiran peserta.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('create-workshop-modal')">
        <span class="material-symbols-outlined" style="font-size:18px;">add</span> Buat Workshop
    </button>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($workshops)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">menu_book</span><h4>Belum ada workshop dibuat</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll mb-md">
    <table>
        <thead>
            <tr><th>Judul</th><th>Chef</th><th>Tanggal</th><th>Peserta</th><th>Status</th><th class="text-right">Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($workshops as $w):
            $statusBadge = ['aktif' => 'badge-success', 'selesai' => 'badge-info', 'dibatalkan' => 'badge-error'][$w['status_workshop']];
        ?>
            <tr>
                <td><?= e($w['judul_workshop']) ?></td>
                <td class="text-muted"><?= e($w['chef_nama']) ?></td>
                <td class="text-muted"><?= date('d M Y', strtotime($w['tanggal_workshop'])) ?>, <?= substr($w['waktu_mulai'],0,5) ?></td>
                <td><?= $w['terdaftar'] ?> / <?= $w['kapasitas_peserta'] ?></td>
                <td><span class="badge <?= $statusBadge ?>"><?= e(ucfirst($w['status_workshop'])) ?></span></td>
                <td class="text-right">
                    <a href="?workshop_id=<?= $w['workshop_id'] ?>" style="color: var(--color-primary); font-weight:600;">Kelola Peserta</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($selectedWorkshopId > 0): ?>
<div class="flex justify-between items-center mb-md">
    <h3 class="section-title" style="margin-bottom:0;">Peserta Workshop</h3>
</div>
<?php if (empty($peserta)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">group_off</span><h4>Belum ada peserta</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead><tr><th>Nama</th><th>Tier</th><th>Metode Bayar</th><th>Status Kehadiran</th><th class="text-right">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($peserta as $p): ?>
            <tr>
                <td><?= e($p['nama_lengkap']) ?></td>
                <td><span class="badge badge-tier-<?= e($p['tier_member']) ?>"><?= e(ucfirst($p['tier_member'])) ?></span></td>
                <td><?= e(ucfirst($p['metode_bayar_workshop'])) ?></td>
                <td>
                    <?php
                    $kehadiranBadge = ['belum' => 'badge-warning', 'hadir' => 'badge-success', 'tidak_hadir' => 'badge-error'][$p['status_kehadiran']];
                    $kehadiranLabel = ['belum' => 'Belum', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak Hadir'][$p['status_kehadiran']];
                    ?>
                    <span class="badge <?= $kehadiranBadge ?>"><?= $kehadiranLabel ?></span>
                </td>
                <td class="text-right">
                    <form method="POST" class="flex gap-sm" style="justify-content:flex-end;">
                        <input type="hidden" name="action" value="konfirmasi_kehadiran">
                        <input type="hidden" name="pendaftaran_id" value="<?= $p['pendaftaran_id'] ?>">
                        <select name="status_kehadiran" class="form-control" style="width:140px; padding:6px 10px; font-size:13px;" onchange="this.form.submit()">
                            <option value="belum" <?= $p['status_kehadiran'] === 'belum' ? 'selected' : '' ?>>Belum</option>
                            <option value="hadir" <?= $p['status_kehadiran'] === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="tidak_hadir" <?= $p['status_kehadiran'] === 'tidak_hadir' ? 'selected' : '' ?>>Tidak Hadir</option>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form-help" style="margin-top:8px;">Status "Hadir" akan otomatis membuat path sertifikat PDF di direktori server dan mengirim notifikasi ke Member.</p>
<?php endif; ?>
<?php endif; ?>

<!-- Modal Buat Workshop -->
<div class="modal-overlay" id="create-workshop-modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <h3>Form Pembuatan Jadwal Workshop</h3>
            <button class="modal-close" onclick="closeModal('create-workshop-modal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="buat_workshop">
            <div class="modal-body">
                <div class="form-group">
                    <label for="judul_workshop">Judul Workshop <span class="required">*</span></label>
                    <input type="text" class="form-control" id="judul_workshop" name="judul_workshop" required minlength="5" maxlength="200">
                </div>
                <div class="form-group">
                    <label for="deskripsi_workshop">Deskripsi Workshop <span class="required">*</span></label>
                    <textarea class="form-control" id="deskripsi_workshop" name="deskripsi_workshop" required minlength="20"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="chef_pengampu_id">Chef Pengampu <span class="required">*</span></label>
                        <select class="form-control" id="chef_pengampu_id" name="chef_pengampu_id" required>
                            <option value="">-- Pilih Chef --</option>
                            <?php foreach ($chefList as $c): ?>
                                <option value="<?= $c['user_id'] ?>"><?= e($c['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="kapasitas_peserta">Kapasitas Peserta <span class="required">*</span></label>
                        <input type="number" class="form-control" id="kapasitas_peserta" name="kapasitas_peserta" required min="1" max="100">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tanggal_workshop">Tanggal <span class="required">*</span></label>
                        <input type="date" class="form-control" id="tanggal_workshop" name="tanggal_workshop" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="waktu_mulai">Waktu Mulai <span class="required">*</span></label>
                        <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Metode Pembayaran <span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="metode_bayar_workshop" value="uang" required> Uang</label>
                        <label class="radio-option"><input type="radio" name="metode_bayar_workshop" value="credits"> Credits</label>
                        <label class="radio-option"><input type="radio" name="metode_bayar_workshop" value="keduanya" checked> Keduanya</label>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="harga_uang">Harga (Rupiah)</label>
                        <input type="number" class="form-control" id="harga_uang" name="harga_uang" min="0" step="1000" value="0">
                    </div>
                    <div class="form-group">
                        <label for="biaya_credits">Biaya Credits</label>
                        <input type="number" class="form-control" id="biaya_credits" name="biaya_credits" min="0" value="0">
                        <p class="form-help">0 berarti gratis</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('create-workshop-modal')">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan Jadwal</span></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
