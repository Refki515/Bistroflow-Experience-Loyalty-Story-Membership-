<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$tanggalMulai = $_GET['mulai'] ?? date('Y-m-d', strtotime('-6 days'));
$tanggalAkhir = $_GET['akhir'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT t.*, u.nama_lengkap FROM transaksi_pos t
     JOIN users u ON u.user_id = t.user_id_member
     WHERE DATE(t.tanggal_transaksi_pos) BETWEEN ? AND ?
     ORDER BY t.tanggal_transaksi_pos DESC"
);
$stmt->execute([$tanggalMulai, $tanggalAkhir]);
$transaksi = $stmt->fetchAll();

$totalNominal = 0;
$totalCredits = 0;
$statusCount = ['berhasil' => 0, 'proses' => 0, 'gagal' => 0];
foreach ($transaksi as $t) {
    $totalNominal += (float)$t['nominal_transaksi'];
    $totalCredits += (int)$t['credits_diberikan'];
    $statusCount[$t['status_sinkronisasi']]++;
}

// Tren harian untuk grafik
$stmt = $pdo->prepare(
    "SELECT DATE(tanggal_transaksi_pos) AS tgl, SUM(nominal_transaksi) AS total
     FROM transaksi_pos WHERE DATE(tanggal_transaksi_pos) BETWEEN ? AND ?
     GROUP BY DATE(tanggal_transaksi_pos) ORDER BY tgl ASC"
);
$stmt->execute([$tanggalMulai, $tanggalAkhir]);
$trendRows = $stmt->fetchAll();
$maxTrend = 1;
foreach ($trendRows as $r) {
    $maxTrend = max($maxTrend, (float)$r['total']);
}

$pageTitle = 'Laporan Transaksi POS';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Laporan Transaksi POS</h2>
        <p>Data transaksi yang diterima dari sistem kasir restoran melalui REST API PHP.</p>
    </div>
</header>

<form method="GET" class="flex gap-sm mb-md" style="flex-wrap:wrap; align-items:flex-end;">
    <div class="form-group" style="margin-bottom:0;">
        <label>Dari Tanggal</label>
        <input type="date" class="form-control" name="mulai" value="<?= e($tanggalMulai) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label>Sampai Tanggal</label>
        <input type="date" class="form-control" name="akhir" value="<?= e($tanggalAkhir) ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Tampilkan</button>
</form>

<div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card"><span class="label">Total Nominal</span><div class="value" style="font-size:22px;"><?= formatRupiah($totalNominal) ?></div></div>
    <div class="stat-card"><span class="label">Total Credits Diberikan</span><div class="value light"><?= formatCredits($totalCredits) ?></div></div>
    <div class="stat-card"><span class="label">Transaksi Berhasil</span><div class="value"><?= $statusCount['berhasil'] ?></div></div>
    <div class="stat-card"><span class="label">Transaksi Gagal/Proses</span><div class="value light"><?= $statusCount['gagal'] + $statusCount['proses'] ?></div></div>
</div>

<?php if (!empty($trendRows)): ?>
<div class="card mb-md">
    <h3 class="section-title">Grafik Tren Nominal Transaksi</h3>
    <div style="display:flex; align-items:flex-end; gap:12px; height:180px; padding-top:24px; border-bottom:1px solid rgba(77,70,53,0.2);">
        <?php foreach ($trendRows as $r):
            $h = max(4, round((float)$r['total'] / $maxTrend * 100));
        ?>
            <div class="flex flex-col items-center gap-sm" style="flex:1;">
                <div style="width:100%; max-width:40px; background: var(--color-primary); border-radius:4px 4px 0 0; height: <?= $h ?>%;" title="<?= formatRupiah($r['total']) ?>"></div>
                <span class="font-mono" style="font-size:10px; color: var(--color-on-surface-variant);"><?= date('d/m', strtotime($r['tgl'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($transaksi)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">receipt</span><h4>Tidak ada transaksi pada rentang tanggal ini</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Tanggal</th><th>Member</th><th class="text-right">Nominal</th><th class="text-right">Credits</th><th class="text-center">Status Sinkronisasi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($transaksi as $t):
            $statusBadge = ['berhasil' => 'badge-success', 'proses' => 'badge-warning', 'gagal' => 'badge-error'][$t['status_sinkronisasi']];
        ?>
            <tr>
                <td class="text-muted"><?= date('d M Y, H:i', strtotime($t['tanggal_transaksi_pos'])) ?></td>
                <td><?= e($t['nama_lengkap']) ?></td>
                <td class="text-right"><?= formatRupiah($t['nominal_transaksi']) ?></td>
                <td class="text-right text-primary-color">+<?= formatCredits($t['credits_diberikan']) ?></td>
                <td class="text-center"><span class="badge <?= $statusBadge ?>"><?= e(ucfirst($t['status_sinkronisasi'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
