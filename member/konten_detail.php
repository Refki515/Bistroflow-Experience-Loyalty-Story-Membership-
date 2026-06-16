<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT k.*, u.nama_lengkap AS pembuat, u.role AS pembuat_role
     FROM konten k JOIN users u ON u.user_id = k.pembuat_konten_id
     WHERE k.konten_id = ? AND k.status_konten = 'tayang'"
);
$stmt->execute([$id]);
$konten = $stmt->fetch();

if (!$konten) {
    header('Location: /member/storytelling.php');
    exit;
}

// Bahan yang berkaitan (jika ada, dari katalog)
$stmt = $pdo->query("SELECT bahan_id, nama_bahan FROM bahan ORDER BY nama_bahan ASC LIMIT 5");
$bahanRelated = $stmt->fetchAll();

$icon = ['video' => 'videocam', 'foto' => 'photo_camera', 'artikel' => 'article'][$konten['jenis_konten']];

$pageTitle = $konten['judul_konten'];
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<a href="<?= url('member/storytelling.php') ?>" class="btn btn-outline"
    style="margin-bottom: var(--space-md); padding:8px 16px; font-size:13px;">
    <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> Kembali ke Galeri
</a>

<div class="card" style="max-width: 800px;">
    <div class="thumb"
        style="height: 320px; border-radius: var(--radius-lg); margin-bottom: var(--space-md); width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; background: var(--color-surface-container-highest);">

        <?php if (!empty($konten['path_file_server'])): ?>
            <img src="<?= e($konten['path_file_server']) ?>" alt="Detail Konten"
                style="width: 100%; height: 100%; object-fit: cover;">
        <?php elseif (!empty($k['path_file_server'])): ?>
            <img src="<?= e($k['path_file_server']) ?>" alt="Detail Konten"
                style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
            <span class="material-symbols-outlined"
                style="font-size:80px; color: var(--color-outline-variant);"><?= $icon ?></span>
        <?php endif; ?>

    </div>

    <span class="badge badge-info" style="margin-bottom: 12px;">
        <span class="material-symbols-outlined" style="font-size:14px;"><?= $icon ?></span>
        <?= e(ucfirst($konten['jenis_konten'])) ?>
    </span>

    <h2 style="font-size: 28px; margin-bottom: 8px;"><?= e($konten['judul_konten']) ?></h2>

    <p class="flex items-center gap-sm" style="color: var(--color-primary); margin-bottom: var(--space-md);">
        <span class="material-symbols-outlined" style="font-size:18px;">person</span>
        <?= e($konten['pembuat']) ?>
        <span class="badge badge-neutral"><?= e(ucfirst($konten['pembuat_role'])) ?></span>
        <span class="text-muted font-mono"
            style="font-size:12px;"><?= date('d M Y', strtotime($konten['tanggal_upload'])) ?></span>
    </p>

    <p style="font-size: 16px; line-height: 1.8; color: var(--color-on-surface-variant);">
        <?= nl2br(e($konten['deskripsi_konten'])) ?>
    </p>

    <?php if (!empty($bahanRelated)): ?>
        <div
            style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid rgba(255,255,255,0.05);">
            <p class="font-mono"
                style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color: var(--color-on-surface-variant); margin-bottom:8px;">
                Bahan terkait di katalog
            </p>
            <div class="flex gap-sm" style="flex-wrap: wrap;">
                <?php foreach ($bahanRelated as $b): ?>
                    <span class="badge badge-neutral"><?= e($b['nama_bahan']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>