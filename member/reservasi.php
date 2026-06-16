<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$tier = currentTier();

$errors = [];
$success = '';

// ============================================================
// PROSES RESERVASI BAHAN MUSIMAN (Modul 5: Form Reservasi Bahan Musiman)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reservasi_bahan') {
    $bahanId = (int)($_POST['bahan_id'] ?? 0);
    $tanggal = $_POST['tanggal_reservasi'] ?? '';

    // PERBAIKAN: Validasi diubah agar fleksibel membaca data musiman (0 atau 1) yang diupload produsen
    $stmt = $pdo->prepare("SELECT * FROM bahan WHERE bahan_id = ?");
    $stmt->execute([$bahanId]);
    $bahan = $stmt->fetch();

    if (!$bahan) {
        $errors[] = 'Bahan tidak ditemukan.';
    } elseif ($bahan['status_ketersediaan'] === 'habis' || $bahan['kuota_reservasi'] <= 0) {
        $errors[] = 'Kuota reservasi bahan ini sudah habis.';
    } elseif ($tanggal === '' || strtotime($tanggal) < strtotime('today')) {
        $errors[] = 'Tanggal reservasi tidak valid atau sudah lewat.';
    } else {
        // Validasi tier akses: Gold akses 72 jam lebih awal, Silver 24 jam
        if ($tier === 'bronze' && $bahan['status_ketersediaan'] === 'terbatas') {
            $errors[] = 'Tier Anda belum memenuhi syarat akses prioritas untuk bahan ini. Upgrade ke Silver atau Gold.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO reservasi (user_id_member, jenis_reservasi, bahan_id, tanggal_reservasi, jumlah_peserta, status_reservasi)
                 VALUES (?, 'bahan_musiman', ?, ?, 1, 'menunggu')"
            );
            $stmt->execute([$userId, $bahanId, $tanggal]);

            // Kurangi kuota secara atomik
            $stmt = $pdo->prepare("UPDATE bahan SET kuota_reservasi = kuota_reservasi - 1 WHERE bahan_id = ? AND kuota_reservasi > 0");
            $stmt->execute([$bahanId]);

            // Jika kuota habis, update status
            $stmt = $pdo->prepare("UPDATE bahan SET status_ketersediaan = 'habis' WHERE bahan_id = ? AND kuota_reservasi <= 0");
            $stmt->execute([$bahanId]);

            $pdo->commit();
            $success = 'Reservasi bahan musiman berhasil diajukan! Menunggu konfirmasi Admin.';
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal memproses reservasi: ' . $ex->getMessage();
        }
    }
}

// PERBAIKAN: Query disesuaikan agar menampilkan semua bahan yang didaftarkan (baik musiman maupun umum)
$stmt = $pdo->query("SELECT * FROM bahan WHERE status_ketersediaan IN ('tersedia', 'terbatas', 'habis') ORDER BY is_musiman DESC, status_ketersediaan ASC");
$bahanList = $stmt->fetchAll();

// Tier akses jam (untuk countdown ilustratif)
$tierAccessHours = ['gold' => 72, 'silver' => 24, 'bronze' => 0];
$accessHours = $tierAccessHours[$tier] ?? 0;

$pageTitle = 'Seasonal Reserves';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php'; 
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .seasonal-featured {
        grid-column: span 2;
        display: flex; flex-wrap: wrap;
        min-height: 320px;
    }
    .seasonal-featured .thumb { flex: 1 1 280px; height: auto; min-height: 240px; }
    .seasonal-featured .body { flex: 1 1 280px; }
    @media (max-width: 900px) { .seasonal-featured { grid-column: span 1; } }
</style>

<header class="page-header">
    <div>
        <span class="eyebrow">Exclusive Member Access</span>
        <h2>Seasonal Reserves</h2>
        <p>Amankan alokasi bahan musiman langka langsung dari jaringan Produsen mitra. Akses prioritas: Gold 72 jam, Silver 24 jam lebih awal.</p>
    </div>
    <div class="badge badge-tier-<?= e($tier) ?>" style="font-size:13px; padding:8px 16px;">
        <span class="material-symbols-outlined" style="font-size:16px; font-variation-settings: 'FILL' 1;">workspace_premium</span>
        <?= e(ucfirst($tier)) ?> Member — Akses <?= $accessHours ?> jam lebih awal
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($bahanList)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">eco</span>
        <h4>Belum ada bahan musiman</h4>
        <p>Pantau halaman ini untuk akses prioritas sesuai tier Anda.</p>
    </div>
<?php else: ?>
<div class="grid-3">
    <?php foreach ($bahanList as $i => $b):
        $habis = $b['status_ketersediaan'] === 'habis' || $b['kuota_reservasi'] <= 0;
        $kuotaMax = max(1, $b['kuota_reservasi'] + 1); 
        $persenSisa = $habis ? 0 : min(100, (int)($b['kuota_reservasi'] / max($b['kuota_reservasi'], 20) * 100));
        $isFeatured = ($i === 0);
        $statusBadge = ['tersedia' => 'badge-success', 'terbatas' => 'badge-warning', 'habis' => 'badge-error'][$b['status_ketersediaan']];
    ?>
        <div class="catalog-card <?= $isFeatured ? 'seasonal-featured' : '' ?>">
            
            <div class="thumb" style="<?= $isFeatured ? '' : 'height:180px;' ?> width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; background: var(--color-surface-container-highest);">
                <?php if (!empty($b['path_file_server'])): ?>
                    <img src="<?= e($b['path_file_server']) ?>" alt="<?= e($b['nama_bahan']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span class="material-symbols-outlined" style="font-size:64px; color: var(--color-on-surface-variant);">eco</span>
                <?php endif; ?>

                <?php if (!$habis): ?>
                <span class="badge badge-error" style="position:absolute; top:12px; left:12px;">
                    <span class="material-symbols-outlined" style="font-size:14px;">timer</span> Live Allocation
                </span>
                <?php endif; ?>
            </div>

            <div class="body">
                <div class="flex justify-between items-start">
                    <h3><?= e($b['nama_bahan']) ?></h3>
                    <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($b['status_ketersediaan'])) ?></span>
                </div>
                <p class="meta"><span class="material-symbols-outlined" style="font-size:14px;">nature_people</span><?= e($b['asal_daerah']) ?> &middot; <?= e($b['nama_petani']) ?></p>

                <?php if ($isFeatured): ?>
                <p style="font-size:14px; color: var(--color-on-surface-variant); margin: 8px 0;">
                    <?= e(mb_strimwidth($b['narasi_cerita_bahan'], 0, 220, '...')) ?>
                </p>
                <?php endif; ?>

                <?php if ($b['sertifikasi']): ?>
                    <span class="badge badge-info" style="align-self: flex-start;"><?= e($b['sertifikasi']) ?></span>
                <?php endif; ?>

                <div style="margin: 8px 0;">
                    <div class="flex justify-between" style="font-size:13px; margin-bottom:6px;">
                        <span class="text-muted">Sisa Kuota</span>
                        <span class="text-primary-color" style="font-weight:700;"><?= max(0, $b['kuota_reservasi']) ?></span>
                    </div>
                    <div class="progress-bar"><div class="progress-bar-fill thin" style="width:<?= $persenSisa ?>%;"></div></div>
                </div>

                <?php if ($b['estimasi_tersedia_berikutnya']): ?>
                    <p class="form-help">Estimasi tersedia: <?= date('d M Y', strtotime($b['estimasi_tersedia_berikutnya'])) ?></p>
                <?php endif; ?>

                <div class="footer-row">
                    <?php if ($accessHours > 0): ?>
                        <span class="font-mono badge badge-neutral countdown-timer" data-seconds="<?= $accessHours * 3600 ?>">
                            <?= sprintf('%02d:00:00', $accessHours) ?>
                        </span>
                    <?php else: ?>
                        <span class="form-help">Akses umum</span>
                    <?php endif; ?>

                    <?php if ($habis): ?>
                        <button class="btn btn-secondary" disabled style="padding:8px 16px; font-size:13px;">Habis</button>
                    <?php elseif ($tier === 'bronze' && $b['status_ketersediaan'] === 'terbatas'): ?>
                        <button class="btn btn-secondary" disabled style="padding:8px 16px; font-size:13px;" title="Tier tidak memenuhi syarat">Terkunci</button>
                    <?php else: ?>
                        <button class="btn btn-primary" style="padding:8px 16px; font-size:13px;"
                            onclick='openReservasiModal(<?= $b['bahan_id'] ?>, <?= json_encode($b['nama_bahan']) ?>)'>
                            Reservasi
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="reservasi-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Form Reservasi Bahan Musiman</h3>
            <button class="modal-close" onclick="closeModal('reservasi-modal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reservasi_bahan">
            <input type="hidden" name="bahan_id" id="modal-bahan-id">
            <div class="modal-body">
                <p style="margin-bottom: var(--space-md);">
                    Bahan: <strong id="modal-bahan-nama" style="color: var(--color-primary);"></strong><br>
                    Tier Anda: <span class="badge badge-tier-<?= e($tier) ?>"><?= e(ucfirst($tier)) ?></span>
                </p>
                <div class="form-group">
                    <label for="tanggal_reservasi">Tanggal Reservasi <span class="required">*</span></label>
                    <input type="date" class="form-control" id="tanggal_reservasi" name="tanggal_reservasi" min="<?= date('Y-m-d') ?>" required>
                    <p class="form-help">Format: YYYY-MM-DD</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reservasi-modal')">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="btn-label">Konfirmasi Reservasi</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function openReservasiModal(id, nama) {
    document.getElementById('modal-bahan-id').value = id;
    document.getElementById('modal-bahan-nama').textContent = nama;
    openModal('reservasi-modal');
}
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', () => openModal('reservasi-modal'));
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>