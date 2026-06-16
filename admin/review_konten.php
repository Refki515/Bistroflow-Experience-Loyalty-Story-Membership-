<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';
$tab = $_GET['tab'] ?? 'pending';
$highlightId = (int) ($_GET['id'] ?? 0);

// ============================================================
// PROSES REVIEW KONTEN (Modul 7: BPMN Review Konten Admin)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $kontenId = (int) ($_POST['konten_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM konten WHERE konten_id = ?");
    $stmt->execute([$kontenId]);
    $konten = $stmt->fetch();

    if (!$konten) {
        $errors[] = 'Konten tidak ditemukan.';
    } else {
        if ($action === 'setujui') {
            $stmt = $pdo->prepare("UPDATE konten SET status_konten = 'tayang', catatan_review = NULL WHERE konten_id = ?");
            $stmt->execute([$kontenId]);
            createNotification((int) $konten['pembuat_konten_id'], 'konten_disetujui', "Konten \"{$konten['judul_konten']}\" telah disetujui dan tayang.", null);
            $success = 'Konten berhasil disetujui dan ditayangkan.';
        } elseif ($action === 'tolak') {
            $catatan = trim($_POST['catatan_review'] ?? '');
            if ($catatan === '') {
                $errors[] = 'Catatan review wajib diisi jika konten ditolak.';
            } else {
                $stmt = $pdo->prepare("UPDATE konten SET status_konten = 'ditolak', catatan_review = ? WHERE konten_id = ?");
                $stmt->execute([$catatan, $kontenId]);
                createNotification((int) $konten['pembuat_konten_id'], 'konten_ditolak', "Konten \"{$konten['judul_konten']}\" ditolak: $catatan", null);
                $success = 'Konten ditolak dan catatan dikirim ke pembuat.';
            }
        } elseif ($action === 'hapus') {
            // ============================================================
            // BARU: Logika Hapus Konten Disetujui
            // ============================================================
            if ($konten['status_konten'] !== 'tayang') {
                $errors[] = 'Hanya konten yang sudah tayang yang bisa dihapus lewat jalur ini.';
            } else {
                // Hard Delete
                $stmt = $pdo->prepare("DELETE FROM konten WHERE konten_id = ?");
                $stmt->execute([$kontenId]);
                // Beri notifikasi ke pembuat konten
                createNotification((int) $konten['pembuat_konten_id'], 'konten_ditolak', "Konten \"{$konten['judul_konten']}\" telah dihapus oleh Admin BistroFlow.", null);
                $success = 'Konten berhasil dihapus secara permanen dari sistem.';
            }
        }
    }
}

$statusMap = ['pending' => 'pending', 'disetujui' => 'tayang', 'ditolak' => 'ditolak'];
$statusFilter = $statusMap[$tab] ?? 'pending';

$stmt = $pdo->prepare(
    "SELECT k.*, u.nama_lengkap AS pembuat, u.role AS pembuat_role FROM konten k
     JOIN users u ON u.user_id = k.pembuat_konten_id
     WHERE k.status_konten = ?
     ORDER BY k.tanggal_upload DESC"
);
$stmt->execute([$statusFilter]);
$kontenList = $stmt->fetchAll();

// Hitung jumlah per tab
$counts = [];
foreach ($statusMap as $key => $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM konten WHERE status_konten = ?");
    $stmt->execute([$status]);
    $counts[$key] = (int) $stmt->fetchColumn();
}

$pageTitle = 'Review Konten';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .tab-pills {
        display: inline-flex;
        background: var(--color-surface-container-low);
        border-radius: var(--radius-full);
        padding: 4px;
        gap: 4px;
    }

    .tab-pill {
        padding: 10px 20px;
        border-radius: var(--radius-full);
        font-size: 13px;
        font-weight: 600;
        color: var(--color-on-surface-variant);
    }

    .tab-pill.active {
        background: var(--color-primary);
        color: var(--color-on-primary);
    }
</style>

<header class="page-header">
    <div>
        <h2>Storytelling Queue</h2>
        <p>Review konten yang diunggah Chef dan Produsen sebelum tayang ke Member.</p>
    </div>
    <div class="tab-pills">
        <a href="?tab=pending" class="tab-pill <?= $tab === 'pending' ? 'active' : '' ?>">Pending
            (<?= $counts['pending'] ?>)</a>
        <a href="?tab=disetujui" class="tab-pill <?= $tab === 'disetujui' ? 'active' : '' ?>">Disetujui
            (<?= $counts['disetujui'] ?>)</a>
        <a href="?tab=ditolak" class="tab-pill <?= $tab === 'ditolak' ? 'active' : '' ?>">Ditolak
            (<?= $counts['ditolak'] ?>)</a>
    </div>
</header>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($kontenList)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">inbox</span>
        <h4>Tidak ada konten pada kategori ini</h4>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($kontenList as $k):
            $icon = ['video' => 'videocam', 'foto' => 'photo_camera', 'artikel' => 'article'][$k['jenis_konten']];
            ?>
            <div class="catalog-card" id="konten-<?= $k['konten_id'] ?>"
                style="<?= $k['konten_id'] === $highlightId ? 'border-color: var(--color-primary);' : '' ?>">
                <div class="thumb"
                    style="width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--color-surface-container-highest); border-radius: var(--radius-lg);">
                    <?php if ($k['jenis_konten'] === 'foto' && !empty($k['path_file_server'])): ?>
                        <img src="<?= e($k['path_file_server']) ?>" alt="<?= e($k['judul_konten']) ?>"
                            style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span class="material-symbols-outlined"
                            style="font-size:48px; color: var(--color-on-surface-variant);"><?= $icon ?></span>
                    <?php endif; ?>
                </div>
                <div class="body">
                    <div class="flex justify-between items-start">
                        <span class="badge badge-info"><?= e(ucfirst($k['jenis_konten'])) ?></span>
                        <span class="badge badge-neutral"><?= e(ucfirst($k['pembuat_role'])) ?></span>
                    </div>
                    <h3><?= e($k['judul_konten']) ?></h3>
                    <p class="meta"><?= e($k['pembuat']) ?> &middot; <?= date('d M Y', strtotime($k['tanggal_upload'])) ?></p>
                    <p
                        style="font-size:13px; color: var(--color-on-surface-variant); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= e($k['deskripsi_konten']) ?>
                    </p>

                    <?php if ($k['status_konten'] === 'ditolak' && $k['catatan_review']): ?>
                        <div class="alert alert-error" style="margin:0; font-size:12px;">Catatan: <?= e($k['catatan_review']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($k['status_konten'] === 'pending'): ?>
                        <div class="footer-row" style="gap:8px;">
                            <form method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="setujui">
                                <input type="hidden" name="konten_id" value="<?= $k['konten_id'] ?>">
                                <button type="submit" class="btn btn-primary w-full"
                                    style="padding:8px; font-size:13px;">Setujui</button>
                            </form>
                            <button type="button" class="btn btn-danger w-full" style="padding:8px; font-size:13px;"
                                onclick='openRejectModal(<?= $k['konten_id'] ?>, <?= json_encode($k['judul_konten']) ?>)'>Tolak</button>
                        </div>
                    <?php elseif ($k['status_konten'] === 'tayang'): ?>
                        <div class="footer-row" style="margin-top: auto;">
                            <button type="button" class="btn btn-danger btn-outline w-full" style="padding:8px; font-size:13px;"
                                onclick='confirmDeleteKonten(<?= $k['konten_id'] ?>, <?= json_encode($k['judul_konten']) ?>)'>
                                <span class="material-symbols-outlined">delete_forever</span>
                                <span class="btn-label">Hapus Konten Permanen</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" id="hidden-delete-form" style="display:none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="konten_id" id="hidden-delete-konten-id">
</form>

<div class="modal-overlay" id="reject-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Tolak Konten</h3>
            <button class="modal-close" onclick="closeModal('reject-modal')"><span
                    class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="tolak">
            <input type="hidden" name="konten_id" id="reject-konten-id">
            <div class="modal-body">
                <p style="margin-bottom:var(--space-md);">Konten: <strong id="reject-konten-title"
                        style="color: var(--color-primary);"></strong></p>
                <div class="form-group">
                    <label for="catatan_review">Catatan Review <span class="required">*</span></label>
                    <textarea class="form-control" id="catatan_review" name="catatan_review" required
                        placeholder="Jelaskan alasan penolakan agar Chef/Produsen dapat memperbaiki..."></textarea>
                    <p class="form-help">Wajib diisi, akan dikirim sebagai notifikasi ke pembuat konten</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reject-modal')">Batal</button>
                <button type="submit" class="btn btn-danger"><span class="btn-label">Tolak Konten Ini</span></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(id, title) {
        document.getElementById('reject-konten-id').value = id;
        document.getElementById('reject-konten-title').textContent = title;
        openModal('reject-modal');
    }

    // BARU: Fungsi Konfirmasi Hapus Konten Disetujui
    function confirmDeleteKonten(id, title) {
        if (confirm("⚠️ PERINGATAN KEKUASAAN ADMIN ⚠️\n\nApakah Anda benar-benar yakin ingin menghapus konten:\n\"" + title + "\" secara PERMANEN dari sistem?\n\nKonten tidak akan bisa dikembalikan lagi setelah dihapus.")) {
            document.getElementById('hidden-delete-konten-id').value = id;
            document.getElementById('hidden-delete-form').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>