<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();

// Revenue hari ini dari transaksi POS
$stmt = $pdo->query("SELECT COALESCE(SUM(nominal_transaksi),0) FROM transaksi_pos WHERE DATE(tanggal_transaksi_pos) = CURDATE()");
$revenueHariIni = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(nominal_transaksi),0) FROM transaksi_pos WHERE DATE(tanggal_transaksi_pos) = CURDATE() - INTERVAL 1 DAY");
$revenueKemarin = (float)$stmt->fetchColumn();
$revenueTrend = $revenueKemarin > 0 ? round(($revenueHariIni - $revenueKemarin) / $revenueKemarin * 100, 1) : 0;

// Member baru minggu ini
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND tanggal_daftar >= CURDATE() - INTERVAL 7 DAY");
$memberBaru = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
$totalMember = (int)$stmt->fetchColumn();

// Konten pending review
$stmt = $pdo->query("SELECT COUNT(*) FROM konten WHERE status_konten = 'pending'");
$pendingReview = (int)$stmt->fetchColumn();

// Total transaksi POS
$stmt = $pdo->query("SELECT COUNT(*) FROM transaksi_pos");
$totalTransaksiPos = (int)$stmt->fetchColumn();

// Reservasi aktif (sebagai "Active Tables" pengganti — konsisten dengan modul reservasi)
$stmt = $pdo->query("SELECT COUNT(*) FROM reservasi WHERE status_reservasi IN ('menunggu','dikonfirmasi')");
$reservasiAktif = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reservasi");
$totalReservasi = (int)$stmt->fetchColumn();

// Tren transaksi POS 7 hari terakhir
$stmt = $pdo->query(
    "SELECT DATE(tanggal_transaksi_pos) AS tgl, SUM(nominal_transaksi) AS total
     FROM transaksi_pos WHERE tanggal_transaksi_pos >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(tanggal_transaksi_pos) ORDER BY tgl ASC"
);
$trendData = $stmt->fetchAll();
$trendMap = [];
foreach ($trendData as $row) {
    $trendMap[$row['tgl']] = (float)$row['total'];
}
$trendValues = [];
$trendLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $trendValues[] = $trendMap[$date] ?? 0;
    $trendLabels[] = date('d/m', strtotime($date));
}
$maxTrend = max(1, max($trendValues));

// Recent POS
$stmt = $pdo->query(
    "SELECT t.*, u.nama_lengkap FROM transaksi_pos t
     JOIN users u ON u.user_id = t.user_id_member
     ORDER BY t.tanggal_transaksi_pos DESC LIMIT 4"
);
$recentPos = $stmt->fetchAll();

// Storytelling Queue
$stmt = $pdo->query(
    "SELECT k.*, u.nama_lengkap AS pembuat, u.role AS pembuat_role FROM konten k
     JOIN users u ON u.user_id = k.pembuat_konten_id
     ORDER BY k.tanggal_upload DESC LIMIT 5"
);
$queue = $stmt->fetchAll();

$pageTitle = 'Command Center';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Command Center</h2>
        <p>Real-time operational metrics and content moderation.</p>
    </div>
    <div class="glass-panel font-mono" style="padding: 10px 16px; font-size:13px; color: var(--color-on-surface-variant); display:flex; align-items:center; gap:8px;">
        <span class="material-symbols-outlined" style="font-size:18px;">calendar_today</span>
        <?= date('d M Y') ?>
    </div>
</header>

<div class="stat-grid" style="grid-template-columns: repeat(5, 1fr);">
    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Daily Revenue</span>
            <span class="material-symbols-outlined" style="color: var(--color-primary); font-variation-settings: 'FILL' 1;">monetization_on</span>
        </div>
        <div class="value" style="font-size:24px;"><?= formatRupiah($revenueHariIni) ?></div>
        <div class="trend <?= $revenueTrend < 0 ? 'warn' : '' ?>">
            <span class="material-symbols-outlined" style="font-size:16px;"><?= $revenueTrend >= 0 ? 'trending_up' : 'trending_down' ?></span>
            <?= $revenueTrend >= 0 ? '+' : '' ?><?= $revenueTrend ?>% vs yesterday
        </div>
    </div>

    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">New Members</span>
            <span class="material-symbols-outlined" style="color: var(--color-secondary);">group_add</span>
        </div>
        <div class="value light"><?= $memberBaru ?></div>
        <div class="trend"><span class="material-symbols-outlined" style="font-size:16px;">trending_up</span> dari <?= $totalMember ?> total</div>
    </div>

    <div class="stat-card" style="border-color: rgba(242,202,80,0.3);">
        <div class="flex justify-between items-center">
            <span class="label" style="color: var(--color-primary);">Pending Reviews</span>
            <span class="material-symbols-outlined" style="color: var(--color-primary); font-variation-settings: 'FILL' 1;">rate_review</span>
        </div>
        <div class="value"><?= $pendingReview ?></div>
        <?php if ($pendingReview > 0): ?>
            <div class="trend warn"><span class="material-symbols-outlined" style="font-size:16px;">warning</span> Action required</div>
        <?php else: ?>
            <div class="trend"><span class="material-symbols-outlined" style="font-size:16px;">check_circle</span> All clear</div>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">POS Trans.</span>
            <span class="material-symbols-outlined" style="color: var(--color-tertiary);">point_of_sale</span>
        </div>
        <div class="value light"><?= $totalTransaksiPos ?></div>
        <div class="trend"><span class="material-symbols-outlined" style="font-size:16px;">horizontal_rule</span> Stable</div>
    </div>

    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Active Reservations</span>
            <span class="material-symbols-outlined" style="color: var(--color-primary);">event_seat</span>
        </div>
        <div class="value light"><?= $reservasiAktif ?> / <?= $totalReservasi ?></div>
        <div class="trend"><?= $totalReservasi > 0 ? round($reservasiAktif / $totalReservasi * 100) : 0 ?>% Active</div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: 2fr 1fr; align-items: start;">
    <!-- Transaction Velocity Chart -->
    <div class="card">
        <div class="flex justify-between items-center mb-md">
            <div>
                <h3 class="section-title" style="margin-bottom:4px;">Transaction Velocity</h3>
                <p class="form-help">Tren transaksi POS 7 hari terakhir.</p>
            </div>
        </div>
        <div style="display:flex; align-items:flex-end; gap:12px; height:220px; padding-top:24px; border-bottom:1px solid rgba(77,70,53,0.2);">
            <?php foreach ($trendValues as $i => $val):
                $heightPercent = max(4, round($val / $maxTrend * 100));
                $isMax = $val == $maxTrend && $val > 0;
            ?>
                <div class="flex flex-col items-center gap-sm" style="flex:1;">
                    <div style="width:100%; max-width:40px; background: <?= $isMax ? 'var(--color-primary)' : 'var(--color-surface-container-high)' ?>; border-radius: 4px 4px 0 0; height: <?= $heightPercent ?>%; position:relative;" title="<?= formatRupiah($val) ?>"></div>
                    <span class="font-mono" style="font-size:10px; color: <?= $isMax ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' ?>;"><?= $trendLabels[$i] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent POS -->
    <div class="card">
        <h3 class="section-title">Recent POS</h3>
        <?php if (empty($recentPos)): ?>
            <div class="empty-state"><span class="material-symbols-outlined icon">receipt</span><h4>Belum ada transaksi</h4></div>
        <?php else: ?>
        <div class="flex flex-col gap-sm">
            <?php foreach ($recentPos as $t): ?>
            <div class="flex justify-between items-center" style="padding:10px 12px; border-radius: var(--radius-lg); background: rgba(45,42,33,0.3);">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined" style="color: var(--color-primary); font-size:18px;">receipt</span>
                    <div>
                        <div style="font-size:14px;"><?= e($t['nama_lengkap']) ?></div>
                        <div class="form-help"><?= date('d M, H:i', strtotime($t['tanggal_transaksi_pos'])) ?></div>
                    </div>
                </div>
                <strong><?= formatRupiah($t['nominal_transaksi']) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Storytelling Queue -->
<div class="flex justify-between items-center mb-md" style="margin-top: var(--space-md);">
    <div>
        <h3 class="section-title" style="margin-bottom:4px;">Storytelling Queue</h3>
        <p class="form-help">Konten yang diunggah Chef dan Produsen yang menunggu moderasi.</p>
    </div>
    <a href="<?= url('admin/review_konten.php') ?>" class="btn btn-outline" style="padding:8px 16px; font-size:13px;">View All Queue</a>
</div>

<?php if (empty($queue)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">inbox</span><h4>Belum ada konten masuk</h4></div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Content Title</th><th>Author</th><th>Type</th><th>Date Submitted</th><th>Status</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($queue as $k):
            $statusBadge = ['pending' => 'badge-warning', 'tayang' => 'badge-info', 'ditolak' => 'badge-error'][$k['status_konten']];
            $statusLabel = ['pending' => 'Pending', 'tayang' => 'Tayang', 'ditolak' => 'Ditolak'][$k['status_konten']];
        ?>
            <tr>
                <td style="font-weight:600; <?= $k['status_konten'] === 'ditolak' ? 'text-decoration:line-through; opacity:0.5;' : '' ?>"><?= e($k['judul_konten']) ?></td>
                <td class="text-muted"><?= e($k['pembuat']) ?> <span class="badge badge-neutral"><?= e(ucfirst($k['pembuat_role'])) ?></span></td>
                <td class="text-muted"><?= e(ucfirst($k['jenis_konten'])) ?></td>
                <td class="text-muted"><?= date('d M, H:i', strtotime($k['tanggal_upload'])) ?></td>
                <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                <td class="text-right">
                    <?php if ($k['status_konten'] === 'pending'): ?>
                        <a href="<?= url('admin/review_konten.php') ?>?id=<?= $k['konten_id'] ?>" style="color: var(--color-primary); font-weight:600;">Review</a>
                    <?php elseif ($k['status_konten'] === 'ditolak'): ?>
                        <a href="<?= url('admin/review_konten.php') ?>?id=<?= e($k['konten_id']) ?>" class="text-muted">View Notes</a>
                    <?php else: ?>
                        <a href="<?= url('admin/review_konten.php') ?>?id=<?= e($k['konten_id']) ?>" class="text-muted">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
