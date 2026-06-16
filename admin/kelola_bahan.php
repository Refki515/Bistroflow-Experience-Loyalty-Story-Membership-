<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';

// ============================================================
// PROSES TAMBAH / EDIT BAHAN (Modul 5: Form Input Bahan oleh Admin)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_bahan') {
    $bahanId = (int) ($_POST['bahan_id'] ?? 0);
    $nama = trim($_POST['nama_bahan'] ?? '');
    $asal = trim($_POST['asal_daerah'] ?? '');
    $narasi = trim($_POST['narasi_cerita_bahan'] ?? '');
    $namaPetani = trim($_POST['nama_petani'] ?? '');
    $sertifikasi = trim($_POST['sertifikasi'] ?? '');
    $status = $_POST['status_ketersediaan'] ?? 'tersedia';
    $isMusiman = isset($_POST['is_musiman']) ? 1 : 0;
    $kuota = (int) ($_POST['kuota_reservasi'] ?? 0);
    $estimasi = $_POST['estimasi_tersedia_berikutnya'] ?? null;
    if ($estimasi === '')
        $estimasi = null;

    if (strlen($nama) < 3)
        $errors[] = 'Nama bahan wajib diisi minimal 3 karakter.';
    if (strlen($asal) < 3)
        $errors[] = 'Asal daerah wajib diisi.';
    if (strlen($narasi) < 50)
        $errors[] = 'Narasi cerita bahan wajib diisi minimal 50 karakter.';
    if (strlen($namaPetani) < 3)
        $errors[] = 'Nama petani wajib diisi.';
    if (!in_array($status, ['tersedia', 'terbatas', 'habis'], true))
        $errors[] = 'Status ketersediaan tidak dikenali.';

    if (empty($errors)) {
        if ($bahanId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE bahan SET nama_bahan=?, asal_daerah=?, narasi_cerita_bahan=?, nama_petani=?, sertifikasi=?, status_ketersediaan=?, is_musiman=?, kuota_reservasi=?, estimasi_tersedia_berikutnya=? WHERE bahan_id=?"
            );
            $stmt->execute([$nama, $asal, $narasi, $namaPetani, $sertifikasi ?: null, $status, $isMusiman, $kuota, $estimasi, $bahanId]);
            $success = 'Data bahan berhasil diperbarui.';
        } else {
            $stmt = $pdo->prepare("SELECT bahan_id FROM bahan WHERE nama_bahan = ?");
            $stmt->execute([$nama]);
            if ($stmt->fetch()) {
                $errors[] = 'Nama bahan sudah terdaftar.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO bahan (nama_bahan, asal_daerah, narasi_cerita_bahan, nama_petani, sertifikasi, status_ketersediaan, is_musiman, kuota_reservasi, estimasi_tersedia_berikutnya)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$nama, $asal, $narasi, $namaPetani, $sertifikasi ?: null, $status, $isMusiman, $kuota, $estimasi]);
                $success = 'Bahan baru berhasil ditambahkan ke katalog.';
            }
        }
    }
}

$stmt = $pdo->query("SELECT b.*, u.nama_lengkap AS produsen_nama FROM bahan b LEFT JOIN users u ON u.user_id = b.produsen_id ORDER BY b.nama_bahan ASC");
$bahanList = $stmt->fetchAll();

$pageTitle = 'Kelola Bahan';
$activeNav = 'admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Kelola Katalog Bahan Eksklusif</h2>
        <p>Kelola data bahan eksklusif beserta status ketersediaan dan kuota reservasi.</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <span class="material-symbols-outlined" style="font-size:18px;">add</span> Tambah Bahan
    </button>
</header>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (empty($bahanList)): ?>
    <div class="card empty-state"><span class="material-symbols-outlined icon">eco</span>
        <h4>Belum ada bahan di katalog</h4>
    </div>
<?php else: ?>
    <div class="table-wrap table-wrap-scroll">
        <table>
            <thead>
                <tr>
                    <th>Nama Bahan</th>
                    <th>Asal</th>
                    <th>Produsen</th>
                    <th>Musiman</th>
                    <th>Kuota</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bahanList as $b):
                    $statusBadge = ['tersedia' => 'badge-success', 'terbatas' => 'badge-warning', 'habis' => 'badge-error'][$b['status_ketersediaan']];
                    ?>
                    <tr>
                        <td><?= e($b['nama_bahan']) ?></td>
                        <td class="text-muted"><?= e($b['asal_daerah']) ?></td>
                        <td class="text-muted"><?= e($b['produsen_nama'] ?? '-') ?></td>
                        <td><?= $b['is_musiman'] ? '<span class="badge badge-info">Ya</span>' : '<span class="badge badge-neutral">Tidak</span>' ?>
                        </td>
                        <td><?= $b['kuota_reservasi'] ?></td>
                        <td><span class="badge <?= $statusBadge ?>"><?= e(ucfirst($b['status_ketersediaan'])) ?></span></td>
                        <td class="text-right">
                            <button class="btn btn-outline" style="padding:6px 14px; font-size:12px;"
                                onclick='openEditBahanModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)'>Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Tambah/Edit Bahan -->
<div class="modal-overlay" id="bahan-modal">
    <div class="modal-box" style="max-width:640px;">
        <div class="modal-header">
            <h3 id="bahan-modal-title">Tambah Bahan</h3>
            <button class="modal-close" onclick="closeModal('bahan-modal')"><span
                    class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="simpan_bahan">
            <input type="hidden" name="bahan_id" id="f-bahan-id" value="0">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Bahan <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nama_bahan" id="f-nama-bahan" required
                            minlength="3" maxlength="150">
                    </div>
                    <div class="form-group">
                        <label>Asal Daerah <span class="required">*</span></label>
                        <input type="text" class="form-control" name="asal_daerah" id="f-asal-daerah" required
                            minlength="3" maxlength="200">
                    </div>
                </div>
                <div class="form-group">
                    <label>Narasi Cerita Bahan <span class="required">*</span></label>
                    <textarea class="form-control" name="narasi_cerita_bahan" id="f-narasi" required minlength="50"
                        style="min-height:120px;"></textarea>
                    <p class="form-help">Minimal 50 karakter</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Petani <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nama_petani" id="f-nama-petani" required
                            minlength="3" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Sertifikasi (opsional)</label>
                        <input type="text" class="form-control" name="sertifikasi" id="f-sertifikasi" maxlength="200">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status Ketersediaan <span class="required">*</span></label>
                        <select class="form-control" name="status_ketersediaan" id="f-status" required>
                            <option value="tersedia">Tersedia</option>
                            <option value="terbatas">Terbatas</option>
                            <option value="habis">Habis</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kuota Reservasi</label>
                        <input type="number" class="form-control" name="kuota_reservasi" id="f-kuota" min="0" value="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="flex items-center gap-sm" style="cursor:pointer;">
                            <input type="checkbox" name="is_musiman" id="f-musiman" value="1"
                                style="accent-color: var(--color-primary);">
                            Bahan musiman
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Estimasi Tersedia Berikutnya</label>
                        <input type="date" class="form-control" name="estimasi_tersedia_berikutnya" id="f-estimasi">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('bahan-modal')">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan</span></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('bahan-modal-title').textContent = 'Tambah Bahan';
        document.getElementById('f-bahan-id').value = 0;
        ['f-nama-bahan', 'f-asal-daerah', 'f-narasi', 'f-nama-petani', 'f-sertifikasi', 'f-estimasi'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('f-status').value = 'tersedia';
        document.getElementById('f-kuota').value = 0;
        document.getElementById('f-musiman').checked = false;
        openModal('bahan-modal');
    }
    function openEditBahanModal(data) {
        document.getElementById('bahan-modal-title').textContent = 'Edit Bahan';
        document.getElementById('f-bahan-id').value = data.bahan_id;
        document.getElementById('f-nama-bahan').value = data.nama_bahan;
        document.getElementById('f-asal-daerah').value = data.asal_daerah;
        document.getElementById('f-narasi').value = data.narasi_cerita_bahan;
        document.getElementById('f-nama-petani').value = data.nama_petani;
        document.getElementById('f-sertifikasi').value = data.sertifikasi || '';
        document.getElementById('f-status').value = data.status_ketersediaan;
        document.getElementById('f-kuota').value = data.kuota_reservasi;
        document.getElementById('f-musiman').checked = data.is_musiman == 1;
        document.getElementById('f-estimasi').value = data.estimasi_tersedia_berikutnya || '';
        openModal('bahan-modal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>