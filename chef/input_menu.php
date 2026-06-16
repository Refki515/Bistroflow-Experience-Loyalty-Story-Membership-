<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['chef', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// Daftar bahan eksklusif untuk dropdown (Modul 9: tabel bahan)
$stmt = $pdo->query("SELECT bahan_id, nama_bahan FROM bahan ORDER BY nama_bahan ASC");
$bahanList = $stmt->fetchAll();

// ============================================================
// PROSES INPUT MENU DAN CERITA (Modul 5: Form Input Menu dan Cerita)
// Disimpan sebagai konten dengan jenis_konten = 'artikel' karena
// belum ada tabel menu tersendiri di skema Modul 9 (konsisten Modul 8: konten)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'input_menu') {
    $namaMenu = trim($_POST['nama_menu'] ?? '');
    $deskripsi = trim($_POST['deskripsi_singkat'] ?? '');
    $narasi = trim($_POST['narasi_cerita'] ?? '');
    $bahanIds = $_POST['bahan_ids'] ?? [];

    if (strlen($namaMenu) < 5) {
        $errors[] = 'Nama menu wajib diisi minimal 5 karakter.';
    }
    if (strlen($narasi) < 20) {
        $errors[] = 'Narasi cerita wajib diisi minimal 20 karakter.';
    }

    if (empty($errors)) {
        $bahanNames = [];
        foreach ($bahanIds as $bid) {
            foreach ($bahanList as $b) {
                if ((int)$b['bahan_id'] === (int)$bid) {
                    $bahanNames[] = $b['nama_bahan'];
                }
            }
        }
        $fullDescription = $deskripsi;
        if (!empty($bahanNames)) {
            $fullDescription .= "\n\nBahan eksklusif terkait: " . implode(', ', $bahanNames);
        }
        $fullDescription .= "\n\n" . $narasi;

        $stmt = $pdo->prepare(
            "INSERT INTO konten (judul_konten, jenis_konten, deskripsi_konten, path_file_server, pembuat_konten_id, status_konten)
             VALUES (?, 'artikel', ?, ?, ?, 'pending')"
        );
        $placeholderPath = UPLOAD_URL_KONTEN . 'menu-' . uniqid() . '.html';
        $stmt->execute([$namaMenu, $fullDescription, $placeholderPath, $userId]);

        $stmt2 = $pdo->query("SELECT user_id FROM users WHERE role = 'admin'");
        foreach ($stmt2->fetchAll() as $admin) {
            createNotification((int)$admin['user_id'], 'workshop_baru', "Menu baru \"$namaMenu\" menunggu review.", 'semua');
        }

        $success = 'Menu dan cerita berhasil disimpan dan menunggu review Admin.';
    }
}

$pageTitle = 'Input Menu dan Cerita';
$activeNav = 'chef';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Input Menu dan Cerita</h2>
        <p>Tambahkan menu baru beserta narasi cerita dan bahan eksklusif yang digunakan.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card" style="max-width: 640px;">
    <form method="POST">
        <input type="hidden" name="action" value="input_menu">

        <div class="form-group">
            <label for="nama_menu">Nama Menu <span class="required">*</span></label>
            <input type="text" class="form-control" id="nama_menu" name="nama_menu" required minlength="5" maxlength="200" placeholder="Contoh: Wagyu A5 Reserve">
            <p class="form-help">Minimal 5 karakter</p>
        </div>

        <div class="form-group">
            <label for="deskripsi_singkat">Deskripsi Singkat</label>
            <textarea class="form-control" id="deskripsi_singkat" name="deskripsi_singkat" placeholder="Deskripsi singkat hidangan (opsional)" style="min-height:60px;"></textarea>
        </div>

        <div class="form-group">
            <label for="narasi_cerita">Narasi Cerita Hidangan <span class="required">*</span></label>
            <textarea class="form-control" id="narasi_cerita" name="narasi_cerita" required minlength="20" placeholder="Ceritakan filosofi, inspirasi, dan proses kreasi menu ini..."></textarea>
            <p class="form-help">Minimal 20 karakter</p>
        </div>

        <div class="form-group">
            <label>Bahan Eksklusif Terkait</label>
            <?php if (empty($bahanList)): ?>
                <p class="form-help">Belum ada bahan eksklusif terdaftar di katalog.</p>
            <?php else: ?>
            <div class="flex flex-col gap-sm" style="max-height:200px; overflow-y:auto; padding: 8px; border: 1px solid rgba(77,70,53,0.3); border-radius: var(--radius-lg);">
                <?php foreach ($bahanList as $b): ?>
                    <label class="flex items-center gap-sm" style="font-size:14px; cursor:pointer;">
                        <input type="checkbox" name="bahan_ids[]" value="<?= $b['bahan_id'] ?>" style="accent-color: var(--color-primary);">
                        <?= e($b['nama_bahan']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="form-help">Pilih satu atau lebih bahan eksklusif yang digunakan pada menu ini</p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary w-full"><span class="btn-label">Simpan Menu</span></button>
        <p class="form-help text-center" style="margin-top:8px;">Menu akan berstatus "Pending" hingga disetujui Admin</p>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
