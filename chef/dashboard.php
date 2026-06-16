<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['chef', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Konten yang diunggah Chef ini
$stmt = $pdo->prepare("SELECT * FROM konten WHERE pembuat_konten_id = ? ORDER BY tanggal_upload DESC LIMIT 6");
$stmt->execute([$userId]);
$kontenList = $stmt->fetchAll();

// Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) FROM konten WHERE pembuat_konten_id = ?");
$stmt->execute([$userId]);
$totalKonten = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM konten WHERE pembuat_konten_id = ? AND status_konten = 'tayang'");
$stmt->execute([$userId]);
$kontenTayang = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM workshops WHERE chef_pengampu_id = ? AND status_workshop = 'aktif' AND tanggal_workshop >= CURDATE()");
$stmt->execute([$userId]);
$workshopMendatang = (int)$stmt->fetchColumn();

// Jadwal workshop chef ini
$stmt = $pdo->prepare(
    "SELECT w.*, (SELECT COUNT(*) FROM pendaftaran_workshop p WHERE p.workshop_id = w.workshop_id) AS terdaftar
     FROM workshops w WHERE w.chef_pengampu_id = ? AND w.status_workshop = 'aktif'
     ORDER BY w.tanggal_workshop ASC LIMIT 3"
);
$stmt->execute([$userId]);
$jadwalWorkshop = $stmt->fetchAll();

$pageTitle = "Chef's Overview";
$activeNav = 'chef';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Workspace</span>
        <h2>Chef's Overview</h2>
        <p>Kelola konten storytelling, menu, dan jadwal workshop Anda.</p>
    </div>
    <div class="flex gap-sm">
        <a href="<?= url('chef/input_menu.php') ?>" class="btn btn-secondary">
            <span class="material-symbols-outlined" style="font-size:18px;">menu_book</span> Tambah Menu Baru
        </a>
        <a href="<?= url('chef/upload_konten.php') ?>" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size:18px; font-variation-settings: 'FILL' 1;">add_circle</span> Tambah Konten Baru
        </a>
    </div>
</header>

<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <span class="label">Total Konten Diunggah</span>
        <div class="value light"><?= $totalKonten ?></div>
    </div>
    <div class="stat-card">
        <span class="label">Konten Tayang</span>
        <div class="value"><?= $kontenTayang ?></div>
    </div>
    <div class="stat-card">
        <span class="label">Workshop Mendatang</span>
        <div class="value light"><?= $workshopMendatang ?></div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: 2fr 1fr; align-items: start;">
    <!-- Pusat Konten & Menu -->
    <div>
        <div class="flex justify-between items-center mb-md">
            <h3 class="section-title" style="margin-bottom:0;">Pusat Konten &amp; Menu</h3>
          <a href="<?= url('chef/upload_konten.php') ?>" class="btn btn-outline" style="padding:8px 16px; font-size:13px;">Lihat Semua</a>
        </div>
        <?php if (empty($kontenList)): ?>
            <div class="card empty-state">
                <span class="material-symbols-outlined icon">upload_file</span>
                <h4>Belum ada konten</h4>
                <p>Unggah konten Behind-the-Scenes atau menu baru untuk ditampilkan kepada Member.</p>
            </div>
        <?php else: ?>
        <div class="card" style="padding: 0; overflow:hidden;">
            <?php foreach ($kontenList as $k):
                $icon = ['video' => 'videocam', 'foto' => 'photo_camera', 'artikel' => 'article'][$k['jenis_konten']];
                $statusBadge = ['pending' => 'badge-warning', 'tayang' => 'badge-success', 'ditolak' => 'badge-error'][$k['status_konten']];
                $statusLabel = ['pending' => 'Pending', 'tayang' => 'Tayang', 'ditolak' => 'Ditolak'][$k['status_konten']];
            ?>
            <div class="flex gap-sm items-center" style="padding: var(--space-md); border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div class="thumb" style="width:64px; height:64px; border-radius: var(--radius-lg); flex-shrink:0;">
                    <span class="material-symbols-outlined" style="font-size:28px;"><?= $icon ?></span>
                </div>
                <div style="flex:1; min-width:0;">
                    <p class="font-mono" style="font-size:11px; color: var(--color-on-surface-variant); text-transform:uppercase; margin-bottom:4px;"><?= e(ucfirst($k['jenis_konten'])) ?></p>
                    <h4 style="font-size:16px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($k['judul_konten']) ?></h4>
                    <p class="form-help" style="margin-top:4px;">Diunggah: <?= date('d M Y', strtotime($k['tanggal_upload'])) ?></p>
                    <?php if ($k['status_konten'] === 'ditolak' && $k['catatan_review']): ?>
                        <p class="form-help text-error-color" title="<?= e($k['catatan_review']) ?>">Catatan: <?= e(mb_strimwidth($k['catatan_review'], 0, 60, '...')) ?></p>
                    <?php endif; ?>
                </div>
                <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Jadwal Workshop -->
    <div>
        <h3 class="section-title flex items-center gap-sm">
            <span class="material-symbols-outlined" style="color: var(--color-primary);">event</span> Jadwal Workshop
        </h3>
        <?php if (empty($jadwalWorkshop)): ?>
            <div class="card empty-state">
                <span class="material-symbols-outlined icon">event_busy</span>
                <h4>Belum ada jadwal</h4>
                <p>Admin akan membuat jadwal workshop untuk Anda.</p>
            </div>
        <?php else: ?>
        <div class="flex flex-col gap-sm">
            <?php foreach ($jadwalWorkshop as $w):
                $sisaSlot = $w['kapasitas_peserta'] - $w['terdaftar'];
                $tanggal = new DateTime($w['tanggal_workshop']);
            ?>
            <div class="card">
                <div class="flex gap-sm" style="align-items:flex-start;">
                    <div class="flex flex-col items-center justify-center" style="width:56px; height:56px; background: var(--color-surface-container-high); border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.05); flex-shrink:0;">
                        <span class="font-mono" style="font-size:11px; text-transform:uppercase; color: var(--color-secondary);"><?= $tanggal->format('M') ?></span>
                        <span style="font-size:18px; font-weight:600;"><?= $tanggal->format('d') ?></span>
                    </div>
                    <div style="flex:1;">
                        <h4 style="font-size:15px; margin-bottom:4px;"><?= e($w['judul_workshop']) ?></h4>
                        <p class="form-help"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">schedule</span> <?= substr($w['waktu_mulai'],0,5) ?> WIB</p>
                        <p class="form-help">
                            <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">group</span>
                            <?= $sisaSlot > 0 ? "Sisa $sisaSlot slot" : 'Fully Booked (' . $w['kapasitas_peserta'] . '/' . $w['kapasitas_peserta'] . ')' ?>
                        </p>
                    </div>
                </div>
                <a href="<?= url('chef/peserta_workshop.php?id=' . $w['workshop_id']) ?>" class="btn btn-outline w-full" style="margin-top: var(--space-sm); font-size:13px;">Kelola Sesi</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
