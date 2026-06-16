<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Saldo credits & tier progress
$saldo = getSaldoCredits($userId);
$progress = getTierProgress($saldo);

// Statistik: workshop diikuti
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran_workshop WHERE user_id_member = ?");
$stmt->execute([$userId]);
$workshopCount = (int) $stmt->fetchColumn();

// Statistik: reservasi aktif
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservasi WHERE user_id_member = ? AND status_reservasi IN ('menunggu','dikonfirmasi')");
$stmt->execute([$userId]);
$reservasiAktif = (int) $stmt->fetchColumn();

// Riwayat credits terbaru (5 baris)
$stmt = $pdo->prepare("SELECT * FROM credits WHERE user_id = ? ORDER BY tanggal_credit DESC LIMIT 5");
$stmt->execute([$userId]);
$riwayatCredits = $stmt->fetchAll();

// Notifikasi terbaru
$stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE penerima_id = ? ORDER BY tanggal_kirim DESC LIMIT 4");
$stmt->execute([$userId]);
$notifikasi = $stmt->fetchAll();

// Bahan musiman tersedia
$stmt = $pdo->prepare(
    "SELECT * FROM bahan WHERE is_musiman = 1 AND status_ketersediaan != 'habis' ORDER BY estimasi_tersedia_berikutnya ASC LIMIT 3"
);
$stmt->execute();
$bahanMusiman = $stmt->fetchAll();

$pageTitle = 'Member Dashboard';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Welcome Back, <?= e(explode(' ', $_SESSION['nama_lengkap'])[0]) ?></h2>
        <p>Here's your culinary profile overview for today.</p>
    </div>
    <div class="flex gap-sm items-center">
       <a href="<?= url('member/notifikasi.php') ?>" class="btn btn-secondary" style="position:relative;">
            <span class="material-symbols-outlined" style="font-size:20px;">notifications</span>
            <?php if (!empty($notifikasi)): ?>
                <span
                    style="position:absolute; top:-2px; right:-2px; width:10px; height:10px; border-radius:50%; background: var(--color-error);"></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Saldo Credits</span>
            <span class="material-symbols-outlined" style="color: var(--color-secondary);">account_balance_wallet</span>
        </div>
        <div class="value"><?= formatCredits($saldo) ?> <span
                style="font-size:14px; color:var(--color-on-surface-variant);">pts</span></div>
    </div>

    <div class="stat-card" style="border-color: rgba(212,175,55,0.3); box-shadow: 0 0 15px rgba(212,175,55,0.08);">
        <div class="flex justify-between items-center">
            <span class="label">Tier Status</span>
            <span class="material-symbols-outlined"
                style="font-variation-settings: 'FILL' 1; color: var(--color-primary);">workspace_premium</span>
        </div>
        <div class="value" style="font-size:24px;"><?= e(ucfirst($progress['current'])) ?> Member</div>
        <?php if ($progress['next']): ?>
            <div>
                <div class="flex justify-between"
                    style="font-size:12px; color: var(--color-on-surface-variant); margin-bottom:6px;">
                    <span>Progress to <?= e(ucfirst($progress['next'])) ?></span>
                    <span><?= $progress['percent'] ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width:<?= $progress['percent'] ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <p class="form-help">Tier tertinggi tercapai</p>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Workshops Followed</span>
            <span class="material-symbols-outlined" style="color: var(--color-tertiary);">menu_book</span>
        </div>
        <div class="value light"><?= $workshopCount ?> <span
                style="font-size:14px; color:var(--color-on-surface-variant);">sessions</span></div>
    </div>

    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Active Reservations</span>
            <span class="material-symbols-outlined" style="color: var(--color-secondary-fixed-dim);">event_seat</span>
        </div>
        <div class="value light"><?= $reservasiAktif ?> <span
                style="font-size:14px; color:var(--color-on-surface-variant);">upcoming</span></div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: 2fr 1fr; align-items: start;">
    <!-- Riwayat Credits -->
    <div class="card">
        <div class="flex justify-between items-center mb-md">
            <h3 class="section-title" style="margin-bottom:0;">Riwayat Credits Terbaru</h3>
            <a href="<?= url('member/loyalty.php') ?>" class="btn btn-outline"
                style="padding:8px 16px; font-size:13px;">View All</a>
        </div>
        <?php if (empty($riwayatCredits)): ?>
            <div class="empty-state">
                <span class="material-symbols-outlined icon">receipt_long</span>
                <h4>Belum ada transaksi credits</h4>
                <p>Lakukan transaksi di restoran untuk mulai mengumpulkan credits.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap table-wrap-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Jenis</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatCredits as $c): ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y', strtotime($c['tanggal_credit'])) ?></td>
                                <td><?= e($c['keterangan_credit'] ?: '-') ?></td>
                                <td>
                                    <?php if ($c['jenis_transaksi_credit'] === 'masuk'): ?>
                                        <span class="badge badge-success">Earned</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Redeemed</span>
                                    <?php endif; ?>
                                </td>
                                <td
                                    class="text-right <?= $c['jenis_transaksi_credit'] === 'masuk' ? 'text-primary-color' : 'text-error-color' ?>">
                                    <?= $c['jenis_transaksi_credit'] === 'masuk' ? '+' : '-' ?>        <?= formatCredits($c['jumlah_credits']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notifikasi -->
    <div class="card">
        <div class="flex justify-between items-center mb-md">
            <h3 class="section-title" style="margin-bottom:0;">Recent Alerts</h3>
        </div>
        <?php if (empty($notifikasi)): ?>
            <div class="empty-state">
                <span class="material-symbols-outlined icon">notifications_off</span>
                <h4>Belum ada notifikasi</h4>
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-sm">
                <?php foreach ($notifikasi as $n):
                    $icon = [
                        'bahan_musiman' => 'eco',
                        'konfirmasi_reservasi' => 'event_available',
                        'workshop_baru' => 'menu_book',
                        'konten_disetujui' => 'check_circle',
                        'konten_ditolak' => 'cancel',
                        'sertifikat_tersedia' => 'workspace_premium',
                    ][$n['jenis_notifikasi']] ?? 'notifications';
                    ?>
                    <div class="flex gap-sm"
                        style="padding:12px; border-radius: var(--radius-lg); background: rgba(45,42,33,0.3);">
                        <span class="material-symbols-outlined"
                            style="color: var(--color-primary); flex-shrink:0;"><?= $icon ?></span>
                        <div>
                            <p style="font-size:14px;"><?= e($n['isi_notifikasi']) ?></p>
                            <p class="font-mono"
                                style="font-size:11px; color: var(--color-on-surface-variant); margin-top:6px;">
                                <?= date('d M Y, H:i', strtotime($n['tanggal_kirim'])) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bahan Musiman Tersedia -->
<div class="flex justify-between items-center mb-md" style="margin-top: var(--space-md);">
    <h3 class="section-title" style="margin-bottom:0;">Bahan Musiman Tersedia</h3>
    <a href="<?= url('member/reservasi.php') ?>" class="btn btn-outline" style="padding:8px 16px; font-size:13px;">Lihat
        Katalog</a>
</div>
<?php if (empty($bahanMusiman)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">eco</span>
        <h4>Belum ada bahan musiman</h4>
        <p>Pantau halaman ini untuk akses prioritas sesuai tier Anda.</p>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($bahanMusiman as $b): ?>
            <div class="catalog-card">
                <div class="thumb"
                    style="width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--color-surface-container-highest); border-radius: var(--radius-lg); position: relative;">
                    <?php if (!empty($b['path_file_server'])): ?>
                        <img src="<?= e($b['path_file_server']) ?>" alt="<?= e($b['nama_bahan']) ?>"
                            style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span class="material-symbols-outlined"
                            style="font-size:48px; color: var(--color-on-surface-variant);">eco</span>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <div class="flex justify-between items-center">
                        <h3><?= e($b['nama_bahan']) ?></h3>
                        <?php
                        $statusBadge = ['tersedia' => 'badge-success', 'terbatas' => 'badge-warning', 'habis' => 'badge-error'][$b['status_ketersediaan']];
                        ?>
                        <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($b['status_ketersediaan'])) ?></span>
                    </div>
                    <p class="meta"><span class="material-symbols-outlined"
                            style="font-size:14px;">nature_people</span><?= e($b['asal_daerah']) ?></p>
                    <div class="footer-row">
                        <span class="font-mono" style="font-size:12px; color: var(--color-on-surface-variant);">Kuota:
                            <?= $b['kuota_reservasi'] ?></span>
                        <a href="<?= url('member/reservasi.php') . '?bahan=' . $b['bahan_id'] ?>" class="btn btn-primary"
                            style="padding:8px 16px; font-size:13px;">Reservasi</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>