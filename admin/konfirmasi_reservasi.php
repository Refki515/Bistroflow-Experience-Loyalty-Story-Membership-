<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';
$tab = $_GET['tab'] ?? 'menunggu';

// ============================================================
// PROSES KONFIRMASI / TOLAK RESERVASI (Modul 5: Form Konfirmasi Reservasi)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reservasiId = (int)($_POST['reservasi_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM reservasi WHERE reservasi_id = ?");
    $stmt->execute([$reservasiId]);
    $reservasi = $stmt->fetch();

    if (!$reservasi) {
        $errors[] = 'Reservasi tidak ditemukan.';
    } else {
        if ($action === 'konfirmasi') {
            $stmt = $pdo->prepare("UPDATE reservasi SET status_reservasi = 'dikonfirmasi' WHERE reservasi_id = ?");
            $stmt->execute([$reservasiId]);
            createNotification((int)$reservasi['user_id_member'], 'konfirmasi_reservasi', 'Reservasi Anda telah dikonfirmasi oleh Admin.', null);
            $success = 'Reservasi berhasil dikonfirmasi.';
        } elseif ($action === 'tolak') {
            $catatan = trim($_POST['catatan_admin'] ?? '');
            if ($catatan === '') {
                $errors[] = 'Catatan Admin wajib diisi jika reservasi ditolak.';
            } else {
                $stmt = $pdo->prepare("UPDATE reservasi SET status_reservasi = 'ditolak', catatan_admin = ? WHERE reservasi_id = ?");
                $stmt->execute([$catatan, $reservasiId]);

                // Kembalikan kuota bahan jika jenis bahan_musiman
                if ($reservasi['jenis_reservasi'] === 'bahan_musiman' && $reservasi['bahan_id']) {
                    $stmt2 = $pdo->prepare("UPDATE bahan SET kuota_reservasi = kuota_reservasi + 1, status_ketersediaan = IF(status_ketersediaan='habis','terbatas',status_ketersediaan) WHERE bahan_id = ?");
                    $stmt2->execute([$reservasi['bahan_id']]);
                }

                // Kembalikan credits jika digunakan
                if ($reservasi['credits_digunakan'] > 0) {
                    $saldo = getSaldoCredits((int)$reservasi['user_id_member']);
                    $saldoBaru = $saldo + (int)$reservasi['credits_digunakan'];
                    $stmt2 = $pdo->prepare("INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit) VALUES (?, ?, ?, 'masuk', 'Pengembalian credits — reservasi ditolak')");
                    $stmt2->execute([$reservasi['user_id_member'], $reservasi['credits_digunakan'], $saldoBaru]);
                    syncTierMember((int)$reservasi['user_id_member'], $saldoBaru);
                }

                createNotification((int)$reservasi['user_id_member'], 'konfirmasi_reservasi', "Reservasi Anda ditolak: $catatan", null);
                $success = 'Reservasi ditolak dan catatan dikirim ke Member.';
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT r.*, u.nama_lengkap, u.tier_member, u.email, b.nama_bahan
     FROM reservasi r
     JOIN users u ON u.user_id = r.user_id_member
     LEFT JOIN bahan b ON b.bahan_id = r.bahan_id
     WHERE r.status_reservasi = ?
     ORDER BY r.tanggal_dibuat DESC"
);
$stmt->execute([$tab]);
$reservasiList = $stmt->fetchAll();

// Hitung jumlah per tab
$counts = [];
foreach (['menunggu', 'dikonfirmasi', 'ditolak', 'dibatalkan'] as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservasi WHERE status_reservasi = ?");
    $stmt->execute([$status]);
    $counts[$status] = (int)$stmt->fetchColumn();
}

$pageTitle = 'Konfirmasi Reservasi';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .tab-pills { display: inline-flex; background: var(--color-surface-container-low); border-radius: var(--radius-full); padding: 4px; gap: 4px; flex-wrap:wrap; }
    .tab-pill { padding: 10px 20px; border-radius: var(--radius-full); font-size: 13px; font-weight: 600; color: var(--color-on-surface-variant); }
    .tab-pill.active { background: var(--color-primary); color: var(--color-on-primary); }
</style>

<header class="page-header">
    <div>
        <h2>Konfirmasi Reservasi</h2>
        <p>Konfirmasi atau tolak permintaan reservasi bahan musiman dan kunjungan petani dari Member.</p>
    </div>
    <div class="tab-pills">
        <a href="?tab=menunggu" class="tab-pill <?= $tab === 'menunggu' ? 'active' : '' ?>">Menunggu (<?= $counts['menunggu'] ?>)</a>
        <a href="?tab=dikonfirmasi" class="tab-pill <?= $tab === 'dikonfirmasi' ? 'active' : '' ?>">Dikonfirmasi (<?= $counts['dikonfirmasi'] ?>)</a>
        <a href="?tab=ditolak" class="tab-pill <?= $tab === 'ditolak' ? 'active' : '' ?>">Ditolak (<?= $counts['ditolak'] ?>)</a>
        <a href="?tab=dibatalkan" class="tab-pill <?= $tab === 'dibatalkan' ? 'active' : '' ?>">Dibatalkan (<?= $counts['dibatalkan'] ?>)</a>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($reservasiList)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">event_busy</span><h4>Tidak ada reservasi pada kategori ini</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Member</th><th>Jenis</th><th>Detail</th><th>Tanggal</th><th>Credits</th><th class="text-right">Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($reservasiList as $r): ?>
            <tr>
                <td>
                    <?= e($r['nama_lengkap']) ?>
                    <span class="badge badge-tier-<?= e($r['tier_member']) ?>"><?= e(ucfirst($r['tier_member'])) ?></span>
                    <p class="form-help"><?= e($r['email']) ?></p>
                </td>
                <td><?= $r['jenis_reservasi'] === 'bahan_musiman' ? 'Bahan Musiman' : 'Kunjungan Petani' ?></td>
                <td class="text-muted">
                    <?= $r['nama_bahan'] ? e($r['nama_bahan']) : 'Kunjungan petani (' . $r['jumlah_peserta'] . ' org)' ?>
                    <?php if ($r['catatan_admin']): ?>
                        <p class="form-help text-error-color">Catatan: <?= e($r['catatan_admin']) ?></p>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= date('d M Y', strtotime($r['tanggal_reservasi'])) ?></td>
                <td><?= $r['credits_digunakan'] > 0 ? formatCredits($r['credits_digunakan']) . ' pts' : '-' ?></td>
                <td class="text-right">
                    <?php if ($r['status_reservasi'] === 'menunggu'): ?>
                    <div class="flex gap-sm" style="justify-content:flex-end;">
                        <form method="POST">
                            <input type="hidden" name="action" value="konfirmasi">
                            <input type="hidden" name="reservasi_id" value="<?= $r['reservasi_id'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding:6px 14px; font-size:12px;">Konfirmasi</button>
                        </form>
                        <button type="button" class="btn btn-danger" style="padding:6px 14px; font-size:12px;"
                            onclick='openTolakModal(<?= $r['reservasi_id'] ?>)'>Tolak</button>
                    </div>
                    <?php else: ?>
                        <span class="badge <?= ['dikonfirmasi'=>'badge-success','ditolak'=>'badge-error','dibatalkan'=>'badge-neutral'][$r['status_reservasi']] ?>">
                            <?= e(ucfirst($r['status_reservasi'])) ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal Tolak Reservasi -->
<div class="modal-overlay" id="tolak-reservasi-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Tolak Reservasi</h3>
            <button class="modal-close" onclick="closeModal('tolak-reservasi-modal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="tolak">
            <input type="hidden" name="reservasi_id" id="tolak-reservasi-id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="catatan_admin">Catatan Admin <span class="required">*</span></label>
                    <textarea class="form-control" id="catatan_admin" name="catatan_admin" required placeholder="Jelaskan alasan penolakan..."></textarea>
                    <p class="form-help">Kuota bahan dan credits yang digunakan akan dikembalikan secara otomatis</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('tolak-reservasi-modal')">Batal</button>
                <button type="submit" class="btn btn-danger"><span class="btn-label">Tolak Reservasi Ini</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function openTolakModal(id) {
    document.getElementById('tolak-reservasi-id').value = id;
    openModal('tolak-reservasi-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
