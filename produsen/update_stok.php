<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['produsen', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare("SELECT * FROM bahan WHERE produsen_id = ? ORDER BY nama_bahan ASC");
$stmt->execute([$userId]);
$bahanList = $stmt->fetchAll();

// ============================================================
// PROSES UPDATE STOK (Modul 5: Form Update Ketersediaan Bahan)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stok') {
    $bahanId = (int)($_POST['bahan_id'] ?? 0);
    $status = $_POST['status_ketersediaan'] ?? '';
    $kuota = (int)($_POST['kuota_reservasi'] ?? 0);
    $estimasi = $_POST['estimasi_tersedia_berikutnya'] ?? null;

    if (!in_array($status, ['tersedia', 'terbatas', 'habis'], true)) {
        $errors[] = 'Status ketersediaan tidak dikenali.';
    }
    if ($kuota < 0) {
        $errors[] = 'Kuota reservasi harus berupa angka tidak negatif.';
    }
    if ($estimasi === '') $estimasi = null;

    $stmt = $pdo->prepare("SELECT * FROM bahan WHERE bahan_id = ? AND produsen_id = ?");
    $stmt->execute([$bahanId, $userId]);
    $bahan = $stmt->fetch();
    if (!$bahan) {
        $errors[] = 'Bahan tidak ditemukan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE bahan SET status_ketersediaan = ?, kuota_reservasi = ?, estimasi_tersedia_berikutnya = ? WHERE bahan_id = ?"
        );
        $stmt->execute([$status, $kuota, $estimasi, $bahanId]);

        // Notifikasi tier-based jika bahan musiman kembali tersedia
        if ($bahan['is_musiman'] && $status === 'tersedia' && $bahan['status_ketersediaan'] === 'habis') {
            $stmt2 = $pdo->query("SELECT user_id, tier_member FROM users WHERE role = 'member'");
            foreach ($stmt2->fetchAll() as $member) {
                createNotification((int)$member['user_id'], 'bahan_musiman', "Bahan musiman \"{$bahan['nama_bahan']}\" kini tersedia kembali.", $member['tier_member']);
            }
        }

        $success = 'Status ketersediaan bahan berhasil diperbarui.';
        $stmt = $pdo->prepare("SELECT * FROM bahan WHERE produsen_id = ? ORDER BY nama_bahan ASC");
        $stmt->execute([$userId]);
        $bahanList = $stmt->fetchAll();
    }
}

$pageTitle = 'Update Stok';
$activeNav = 'produsen';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Update Ketersediaan Bahan</h2>
        <p>Perbarui status stok dan kuota reservasi bahan eksklusif Anda. Perubahan langsung terlihat di katalog Member.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($bahanList)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">inventory_2</span>
        <h4>Belum ada bahan terdaftar</h4>
        <p><a href="/produsen/upload_bahan.php" style="color: var(--color-primary);">Unggah cerita bahan baru</a> untuk mulai mengisi katalog.</p>
    </div>
<?php else: ?>
<div class="grid-3">
    <?php foreach ($bahanList as $b):
        $statusBadge = ['tersedia' => 'badge-success', 'terbatas' => 'badge-warning', 'habis' => 'badge-error'][$b['status_ketersediaan']];
    ?>
        <div class="card">
            <div class="flex justify-between items-start mb-sm">
                <h3 style="font-size:16px;"><?= e($b['nama_bahan']) ?></h3>
                <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($b['status_ketersediaan'])) ?></span>
            </div>
            <p class="form-help mb-sm">Kuota saat ini: <?= $b['kuota_reservasi'] ?> <?= $b['is_musiman'] ? '(musiman)' : '' ?></p>
            <form method="POST">
                <input type="hidden" name="action" value="update_stok">
                <input type="hidden" name="bahan_id" value="<?= $b['bahan_id'] ?>">

                <div class="form-group">
                    <label>Status Ketersediaan <span class="required">*</span></label>
                    <select class="form-control" name="status_ketersediaan" required>
                        <option value="tersedia" <?= $b['status_ketersediaan'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="terbatas" <?= $b['status_ketersediaan'] === 'terbatas' ? 'selected' : '' ?>>Terbatas</option>
                        <option value="habis" <?= $b['status_ketersediaan'] === 'habis' ? 'selected' : '' ?>>Habis</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Kuota / Jumlah Stok</label>
                    <input type="number" class="form-control" name="kuota_reservasi" min="0" value="<?= $b['kuota_reservasi'] ?>">
                </div>

                <div class="form-group">
                    <label>Estimasi Tersedia Berikutnya</label>
                    <input type="date" class="form-control" name="estimasi_tersedia_berikutnya" value="<?= e($b['estimasi_tersedia_berikutnya'] ?? '') ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>

                <button type="submit" class="btn btn-primary w-full"><span class="btn-label">Update Stok</span></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
