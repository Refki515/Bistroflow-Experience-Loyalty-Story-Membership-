<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['chef', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$workshopId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM workshops WHERE workshop_id = ? AND chef_pengampu_id = ?");
$stmt->execute([$workshopId, $userId]);
$workshop = $stmt->fetch();

if (!$workshop) {
    header('Location: /chef/dashboard.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.*, u.nama_lengkap, u.email, u.tier_member
     FROM pendaftaran_workshop p
     JOIN users u ON u.user_id = p.user_id_member
     WHERE p.workshop_id = ?
     ORDER BY p.tanggal_daftar ASC"
);
$stmt->execute([$workshopId]);
$peserta = $stmt->fetchAll();

$pageTitle = 'Daftar Peserta Workshop';
$activeNav = 'chef';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<a href="/chef/dashboard.php" class="btn btn-outline" style="margin-bottom: var(--space-md); padding:8px 16px; font-size:13px;">
    <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Kembali
</a>

<header class="page-header">
    <div>
        <h2><?= e($workshop['judul_workshop']) ?></h2>
        <p><?= date('d M Y', strtotime($workshop['tanggal_workshop'])) ?> &middot; <?= substr($workshop['waktu_mulai'], 0, 5) ?> WIB &middot; Kapasitas <?= $workshop['kapasitas_peserta'] ?> peserta</p>
    </div>
</header>

<?php if (empty($peserta)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">group_off</span>
        <h4>Belum ada peserta terdaftar</h4>
    </div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Nama Peserta</th><th>Tier</th><th>Metode Bayar</th><th>Status Kehadiran</th><th>Tanggal Daftar</th></tr>
        </thead>
        <tbody>
        <?php foreach ($peserta as $p):
            $kehadiranBadge = ['belum' => 'badge-warning', 'hadir' => 'badge-success', 'tidak_hadir' => 'badge-error'][$p['status_kehadiran']];
            $kehadiranLabel = ['belum' => 'Belum Konfirmasi', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak Hadir'][$p['status_kehadiran']];
        ?>
            <tr>
                <td><?= e($p['nama_lengkap']) ?><br><span class="form-help"><?= e($p['email']) ?></span></td>
                <td><span class="badge badge-tier-<?= e($p['tier_member']) ?>"><?= e(ucfirst($p['tier_member'])) ?></span></td>
                <td><?= e(ucfirst($p['metode_bayar_workshop'])) ?></td>
                <td><span class="badge <?= $kehadiranBadge ?>"><?= $kehadiranLabel ?></span></td>
                <td class="text-muted"><?= date('d M Y, H:i', strtotime($p['tanggal_daftar'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form-help" style="margin-top:8px;">Konfirmasi kehadiran dan penerbitan sertifikat dikelola oleh Admin.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
