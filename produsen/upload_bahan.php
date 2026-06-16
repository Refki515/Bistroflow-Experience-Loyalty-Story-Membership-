<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['produsen', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// ============================================================
// PROSES UPLOAD CERITA ASAL BAHAN (Modul 5: Form Upload Bahan)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_bahan') {
    $nama = trim($_POST['nama_bahan'] ?? '');
    $asal = trim($_POST['asal_daerah'] ?? '');
    $narasi = trim($_POST['narasi_cerita_bahan'] ?? '');
    $namaPetani = trim($_POST['nama_petani'] ?? '');
    $sertifikasi = trim($_POST['sertifikasi'] ?? '');
    $isMusiman = isset($_POST['is_musiman']) ? 1 : 0;
    $kuota = (int)($_POST['kuota_reservasi'] ?? 0);
    $estimasi = $_POST['estimasi_tersedia_berikutnya'] ?? null;

    if (strlen($nama) < 3) {
        $errors[] = 'Nama bahan wajib diisi minimal 3 karakter.';
    }
    if (strlen($asal) < 3) {
        $errors[] = 'Asal daerah bahan wajib diisi.';
    }
    if (strlen($narasi) < 50) {
        $errors[] = 'Narasi cerita bahan wajib diisi minimal 50 karakter.';
    }
    if (strlen($namaPetani) < 3) {
        $errors[] = 'Nama petani/produsen wajib diisi.';
    }
    if ($isMusiman && $kuota <= 0) {
        $errors[] = 'Kuota reservasi harus diisi jika bahan bersifat musiman.';
    }
    if ($estimasi === '') $estimasi = null;

    // File upload opsional (foto/video lahan)
    $filePath = null;
    if (!empty($_FILES['file_bahan']['name'])) {
        $uploadResult = handleFileUpload($_FILES['file_bahan'], UPLOAD_DIR_BAHAN, UPLOAD_URL_BAHAN);
        if (!$uploadResult['success']) {
            $errors[] = $uploadResult['error'];
        } else {
            $filePath = $uploadResult['path'];
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT bahan_id FROM bahan WHERE nama_bahan = ?");
        $stmt->execute([$nama]);
        if ($stmt->fetch()) {
            $errors[] = 'Nama bahan sudah terdaftar di sistem.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO bahan (nama_bahan, asal_daerah, narasi_cerita_bahan, nama_petani, sertifikasi, status_ketersediaan, is_musiman, kuota_reservasi, estimasi_tersedia_berikutnya, path_file_server, produsen_id)
                 VALUES (?, ?, ?, ?, ?, 'tersedia', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nama, $asal, $narasi, $namaPetani, $sertifikasi ?: null, $isMusiman, $kuota, $estimasi, $filePath, $userId]);
            $success = 'Cerita bahan baru berhasil disimpan ke katalog.';
        }
    }
}

$pageTitle = 'Unggah Cerita Bahan';
$activeNav = 'produsen';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Unggah Cerita Asal Bahan</h2>
        <p>Tambahkan bahan eksklusif baru lengkap dengan cerita petani, lokasi asal, dan informasi ketersediaan.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card" style="max-width: 720px;">
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_bahan">

        <div class="form-row">
            <div class="form-group">
                <label for="nama_bahan">Nama Bahan <span class="required">*</span></label>
                <input type="text" class="form-control" id="nama_bahan" name="nama_bahan" required minlength="3" maxlength="150" placeholder="Contoh: Alba White Truffle">
            </div>
            <div class="form-group">
                <label for="asal_daerah">Asal Daerah <span class="required">*</span></label>
                <input type="text" class="form-control" id="asal_daerah" name="asal_daerah" required minlength="3" maxlength="200" placeholder="Contoh: Piedmont, Italia">
            </div>
        </div>

        <div class="form-group">
            <label for="narasi_cerita_bahan">Narasi Cerita Bahan <span class="required">*</span></label>
            <textarea class="form-control" id="narasi_cerita_bahan" name="narasi_cerita_bahan" required minlength="50" placeholder="Ceritakan metode budidaya, nilai budaya, dan perjalanan bahan ke meja makan..." style="min-height:140px;"></textarea>
            <p class="form-help">Minimal 50 karakter</p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="nama_petani">Nama Petani / Mitra <span class="required">*</span></label>
                <input type="text" class="form-control" id="nama_petani" name="nama_petani" required minlength="3" maxlength="100" placeholder="Contoh: Tartufi Morra">
            </div>
            <div class="form-group">
                <label for="sertifikasi">Sertifikasi (opsional)</label>
                <input type="text" class="form-control" id="sertifikasi" name="sertifikasi" maxlength="200" placeholder="Contoh: Slow Food Presidia">
            </div>
        </div>

        <div class="form-group">
            <label for="file_bahan">Foto / Video Lahan (opsional)</label>
            <label class="file-drop" for="file_bahan" id="drop-zone">
                <div class="icon"><span class="material-symbols-outlined" style="font-size:32px; color: var(--color-primary);" id="upload-icon">upload_file</span></div>
                <p class="file-drop-label" id="file-label">Klik untuk pilih file atau seret ke sini</p>
                <p class="form-help">Format: jpg, png, mp4. Maks. 50MB</p>
            </label>
            <input type="file" id="file_bahan" name="file_bahan" accept=".jpg,.jpeg,.png,.mp4" style="display:none;" onchange="displayFileName()">
        </div>

        <div class="form-group">
            <label class="flex items-center gap-sm" style="cursor:pointer;">
                <input type="checkbox" id="is_musiman" name="is_musiman" value="1" style="accent-color: var(--color-primary);" onchange="toggleMusimanFields()">
                Bahan ini bersifat musiman (eksklusif berdasarkan musim)
            </label>
        </div>

        <div id="musiman-fields" style="display:none;">
            <div class="form-row">
                <div class="form-group">
                    <label for="kuota_reservasi">Kuota Reservasi <span class="required">*</span></label>
                    <input type="number" class="form-control" id="kuota_reservasi" name="kuota_reservasi" min="0" placeholder="Contoh: 12">
                </div>
                <div class="form-group">
                    <label for="estimasi_tersedia_berikutnya">Estimasi Tersedia Berikutnya</label>
                    <input type="date" class="form-control" id="estimasi_tersedia_berikutnya" name="estimasi_tersedia_berikutnya" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-full"><span class="btn-label">Simpan ke Katalog</span></button>
    </form>
</div>

<script>
function toggleMusimanFields() {
    const checked = document.getElementById('is_musiman').checked;
    document.getElementById('musiman-fields').style.display = checked ? 'block' : 'none';
    document.getElementById('kuota_reservasi').required = checked;
}

// BARU: Fungsi pelacak file agar nama foto terdeteksi penuh sebelum disubmit ke PHP
function displayFileName() {
    const fileInput = document.getElementById('file_bahan');
    const fileLabel = document.getElementById('file-label');
    const uploadIcon = document.getElementById('upload-icon');
    const dropZone = document.getElementById('drop-zone');

    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        fileLabel.innerHTML = `<strong>File Siap Diunggah:</strong> ${fileName}`;
        uploadIcon.textContent = "check_circle"; // Mengubah ikon menjadi simbol centang berhasil
        dropZone.style.borderColor = "var(--color-primary)";
    } else {
        fileLabel.textContent = "Klik untuk pilih file atau seret ke sini";
        uploadIcon.textContent = "upload_file";
        dropZone.style.borderColor = "";
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>