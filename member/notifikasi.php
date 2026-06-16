<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Tandai semua sudah dibaca
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifikasi SET status_pengiriman = 'terkirim' WHERE penerima_id = ? AND status_pengiriman = 'belum_dibaca'");
    $stmt->execute([$userId]);
}

$stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE penerima_id = ? ORDER BY tanggal_kirim DESC");
$stmt->execute([$userId]);
$notifikasi = $stmt->fetchAll();

$pageTitle = 'Notifikasi';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Recent Alerts</h2>
        <p>Semua notifikasi terkait credits, reservasi, workshop, dan konten Anda.</p>
    </div>
    <?php if (!empty($notifikasi)): ?>
    <form method="POST">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-outline">Mark All Read</button>
    </form>
    <?php endif; ?>
</header>

<?php if (empty($notifikasi)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">notifications_off</span>
        <h4>Belum ada notifikasi</h4>
    </div>
<?php else: ?>
<div class="card">
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
        $belumDibaca = $n['status_pengiriman'] === 'belum_dibaca';
    ?>
        <div class="flex gap-sm" style="padding:14px; border-radius: var(--radius-lg); background: <?= $belumDibaca ? 'rgba(242,202,80,0.06)' : 'rgba(45,42,33,0.3)' ?>; border: 1px solid <?= $belumDibaca ? 'rgba(242,202,80,0.2)' : 'transparent' ?>;">
            <span class="material-symbols-outlined" style="color: var(--color-primary); flex-shrink:0;"><?= $icon ?></span>
            <div style="flex:1;">
                <p style="font-size:14px;"><?= e($n['isi_notifikasi']) ?></p>
                <p class="font-mono" style="font-size:11px; color: var(--color-on-surface-variant); margin-top:6px;">
                    <?= date('d M Y, H:i', strtotime($n['tanggal_kirim'])) ?>
                </p>
            </div>
            <?php if ($belumDibaca): ?>
                <span style="width:8px; height:8px; border-radius:50%; background: var(--color-primary); flex-shrink:0; margin-top:4px;"></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
