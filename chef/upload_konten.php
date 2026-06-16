<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['chef', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// ============================================================
// PROSES UPLOAD KONTEN (Modul 5: Form Upload Konten Behind-the-Scenes)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_konten') {
    $judul = trim($_POST['judul_konten'] ?? '');
    $jenis = $_POST['jenis_konten'] ?? '';
    $deskripsi = trim($_POST['deskripsi_konten'] ?? '');

    if (strlen($judul) < 5) {
        $errors[] = 'Judul konten wajib diisi minimal 5 karakter.';
    }
    if (!in_array($jenis, ['video', 'foto', 'artikel'], true)) {
        $errors[] = 'Jenis konten tidak dikenali.';
    }
    if (strlen($deskripsi) < 20) {
        $errors[] = 'Deskripsi konten wajib diisi minimal 20 karakter.';
    }

    $uploadResult = null;
    if (empty($errors)) {
        if ($jenis === 'artikel' && empty($_FILES['file_konten']['name'])) {
            // Artikel boleh tanpa file fisik; gunakan placeholder path
            $uploadResult = ['success' => true, 'path' => UPLOAD_URL_KONTEN . 'artikel-' . uniqid() . '.html', 'error' => null];
        } else {
            // PERBAIKAN VALIDASI: Memastikan file benar-benar masuk ke $_FILES sebelum diproses
            if (!isset($_FILES['file_konten']) || $_FILES['file_konten']['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Wajib memilih file untuk jenis konten Foto atau Video.';
            } else {
                $uploadResult = handleFileUpload($_FILES['file_konten'], UPLOAD_DIR_KONTEN, UPLOAD_URL_KONTEN);
                if (!$uploadResult['success']) {
                    $errors[] = $uploadResult['error'];
                }
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO konten (judul_konten, jenis_konten, deskripsi_konten, path_file_server, pembuat_konten_id, status_konten)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$judul, $jenis, $deskripsi, $uploadResult['path'], $userId]);

        // Menggunakan jenis_notifikasi 'konten_ditolak' atau disesuaikan agar tidak melanggar batasan ENUM database
        $stmt2 = $pdo->query("SELECT user_id FROM users WHERE role = 'admin'");
        foreach ($stmt2->fetchAll() as $admin) {
            createNotification((int)$admin['user_id'], 'konten_ditolak', "Konten baru \"$judul\" menunggu review.", 'semua');
        }

        $success = 'Konten berhasil diunggah dan menunggu review Admin.';
    }
}

// Daftar konten milik Chef ini
$stmt = $pdo->prepare("SELECT * FROM konten WHERE pembuat_konten_id = ? ORDER BY tanggal_upload DESC");
$stmt->execute([$userId]);
$kontenList = $stmt->fetchAll();

$pageTitle = 'Upload Konten';
$activeNav = 'chef';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Upload Konten Behind-the-Scenes</h2>
        <p>Bagikan cerita di balik pencarian bahan dan pengembangan menu kepada Member.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="stat-grid" style="grid-template-columns: 1fr 1.4fr; align-items:start;">
    <div class="card">
        <h3 class="section-title">Form Upload Konten</h3>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_konten">

            <div class="form-group">
                <label for="judul_konten">Judul Konten <span class="required">*</span></label>
                <input type="text" class="form-control" id="judul_konten" name="judul_konten" required minlength="5" maxlength="200" placeholder="Contoh: The Art of Fermentation">
                <p class="form-help">Minimal 5 karakter, maksimal 200 karakter</p>
            </div>

            <div class="form-group">
                <label for="jenis_konten">Jenis Konten <span class="required">*</span></label>
                <select class="form-control" id="jenis_konten" name="jenis_konten" required onchange="toggleFileRequired()">
                    <option value="">-- Pilih jenis --</option>
                    <option value="video">Video</option>
                    <option value="foto">Foto</option>
                    <option value="artikel">Artikel</option>
                </select>
            </div>

            <div class="form-group">
                <label for="file_konten">Unggah File <span class="required" id="file-required-mark">*</span></label>
                <label class="file-drop" for="file_konten" id="drop-zone">
                    <div class="icon"><span class="material-symbols-outlined" style="font-size:32px; color: var(--color-primary);" id="upload-icon">upload_file</span></div>
                    <p class="file-drop-label" id="file-label">Klik untuk pilih file atau seret ke sini</p>
                    <p class="form-help">Format: jpg, png, mp4. Maks. 50MB</p>
                </label>
                <input type="file" id="file_konten" name="file_konten" accept=".jpg,.jpeg,.png,.mp4" style="display:none;" onchange="displayFileName()">
            </div>

            <div class="form-group">
                <label for="deskripsi_konten">Deskripsi / Narasi <span class="required">*</span></label>
                <textarea class="form-control" id="deskripsi_konten" name="deskripsi_konten" required minlength="20" placeholder="Ceritakan proses, inspirasi, atau perjalanan di balik konten ini..." style="min-height: 120px;"></textarea>
                <p class="form-help">Minimal 20 karakter</p>
            </div>

            <button type="submit" class="btn btn-primary w-full"><span class="btn-label">Unggah Konten</span></button>
            <p class="form-help text-center" style="margin-top:8px;">Konten akan berstatus "Pending" hingga disetujui Admin</p>
        </form>
    </div>

    <div>
        <h3 class="section-title">Konten Saya</h3>
        <?php if (empty($kontenList)): ?>
            <div class="card empty-state">
                <span class="material-symbols-outlined icon">collections</span>
                <h4>Belum ada konten diunggah</h4>
            </div>
        <?php else: ?>
        <div class="table-wrap table-wrap-scroll">
            <table>
                <thead><tr><th>Judul</th><th>Jenis</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                <?php foreach ($kontenList as $k):
                    $statusBadge = ['pending' => 'badge-warning', 'tayang' => 'badge-success', 'ditolak' => 'badge-error'][$k['status_konten']];
                    $statusLabel = ['pending' => 'Pending', 'tayang' => 'Tayang', 'ditolak' => 'Ditolak'][$k['status_konten']];
                ?>
                    <tr>
                        <td>
                            <strong><?= e($k['judul_konten']) ?></strong>
                            <?php if ($k['status_konten'] === 'ditolak' && $k['catatan_review']): ?>
                                <p class="form-help text-error-color" style="color: var(--color-error, #ff4d4d); margin-top: 4px;">Catatan: <?= e($k['catatan_review']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?= e(ucfirst($k['jenis_konten'])) ?></td>
                        <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                        <td class="text-muted"><?= date('d M Y', strtotime($k['tanggal_upload'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFileRequired() {
    const jenis = document.getElementById('jenis_konten').value;
    const fileInput = document.getElementById('file_konten');
    const mark = document.getElementById('file-required-mark');
    if (jenis === 'artikel') {
        fileInput.required = false;
        mark.style.display = 'none';
    } else {
        fileInput.required = true;
        mark.style.display = 'inline';
    }
}

// BARU: Fungsi untuk menampilkan nama file foto/video yang dipilih agar terdeteksi oleh user
function displayFileName() {
    const fileInput = document.getElementById('file_konten');
    const fileLabel = document.getElementById('file-label');
    const uploadIcon = document.getElementById('upload-icon');
    const dropZone = document.getElementById('drop-zone');

    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        fileLabel.innerHTML = `<strong>File Terpilih:</strong> ${fileName}`;
        uploadIcon.textContent = "check_circle"; // Mengubah ikon menjadi tanda centang sukses
        dropZone.style.borderColor = "var(--color-primary)";
    } else {
        fileLabel.textContent = "Klik untuk pilih file atau seret ke sini";
        uploadIcon.textContent = "upload_file";
        dropZone.style.borderColor = "";
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>