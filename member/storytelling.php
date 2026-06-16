<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$filter = $_GET['jenis'] ?? 'all';

$sql = "SELECT k.*, u.nama_lengkap AS pembuat, u.role AS pembuat_role
        FROM konten k
        JOIN users u ON u.user_id = k.pembuat_konten_id
        WHERE k.status_konten = 'tayang'";

if (in_array($filter, ['video', 'foto', 'artikel'], true)) {
    $sql .= " AND k.jenis_konten = :jenis";
}
$sql .= " ORDER BY k.tanggal_upload DESC";

$stmt = $pdo->prepare($sql);
if (in_array($filter, ['video', 'foto', 'artikel'], true)) {
    $stmt->bindValue(':jenis', $filter);
}
$stmt->execute();
$kontenList = $stmt->fetchAll();

$pageTitle = 'Culinary Storytelling';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .filter-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 8px;
        margin-bottom: var(--space-md);
    }

    .filter-tab {
        flex-shrink: 0;
        padding: 8px 24px;
        border-radius: var(--radius-full);
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid var(--color-outline-variant);
        color: var(--color-on-surface-variant);
    }

    .filter-tab.active {
        background-color: var(--color-primary);
        color: var(--color-on-primary);
        border-color: var(--color-primary);
    }

    .filter-tab:hover:not(.active) {
        border-color: var(--color-primary);
        color: var(--color-on-background);
    }

    .story-thumb-icon {
        font-size: 56px;
        color: var(--color-outline-variant);
    }
</style>

<header class="page-header">
    <div>
        <h2>Culinary Narratives</h2>
        <p>Telusuri asal-usul, teknik, dan keahlian di balik menu musiman kami melalui cerita dari Chef dan Produsen.
        </p>
    </div>
</header>

<div class="filter-tabs">
    <a href="?jenis=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?jenis=video" class="filter-tab <?= $filter === 'video' ? 'active' : '' ?>">Video</a>
    <a href="?jenis=foto" class="filter-tab <?= $filter === 'foto' ? 'active' : '' ?>">Photo</a>
    <a href="?jenis=artikel" class="filter-tab <?= $filter === 'artikel' ? 'active' : '' ?>">Article</a>
</div>

<?php if (empty($kontenList)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">collections</span>
        <h4>Belum ada konten</h4>
        <p>Konten storytelling akan muncul di sini setelah disetujui Admin.</p>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($kontenList as $k):
            $icon = ['video' => 'videocam', 'foto' => 'photo_camera', 'artikel' => 'article'][$k['jenis_konten']];
            ?>
            <a href="<?= $base ?>member/konten_detail.php?id=<?= $k['konten_id'] ?>" class="catalog-card">
                <div class="thumb"
                    style="width: 100%; height: 180px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; background: var(--color-surface-container-highest);">

                    <?php if (!empty($k['path_file_server'])): ?>
                        <img src="<?= e($k['path_file_server']) ?>" alt="Media Konten"
                            style="width: 100%; height: 100%; object-fit: cover;">
                    <?php elseif (!empty($k['url_media'])): ?>
                        <img src="<?= e($k['url_media']) ?>" alt="Media Konten"
                            style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span class="material-symbols-outlined story-thumb-icon"
                            style="font-size: 48px; color: var(--color-on-surface-variant);"><?= $icon ?></span>
                    <?php endif; ?>

                    <span class="badge badge-info"
                        style="position:absolute; top:12px; left:12px; display: flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size:14px;"><?= $icon ?></span>
                        <?= e(ucfirst($k['jenis_konten'])) ?>
                    </span>
                </div>
                <div class="body">
                    <h3><?= e($k['judul_konten']) ?></h3>
                    <p class="meta" style="color: var(--color-primary);">
                        <?= e($k['pembuat']) ?>
                        <span class="badge badge-neutral" style="margin-left:4px;"><?= e(ucfirst($k['pembuat_role'])) ?></span>
                    </p>
                    <p
                        style="font-size:14px; color: var(--color-on-surface-variant); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= e($k['deskripsi_konten']) ?>
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>