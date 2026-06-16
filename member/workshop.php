<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$config = getKonfigurasiCredits();
$saldo = getSaldoCredits($userId);

$tab = $_GET['tab'] ?? 'upcoming';
$errors = [];
$success = '';

// ============================================================
// PROSES PENDAFTARAN WORKSHOP (Modul 5: Form Pendaftaran Workshop)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'daftar_workshop') {
    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $metode = $_POST['metode_bayar'] ?? '';

    if (!in_array($metode, ['uang', 'credits'], true)) {
        $errors[] = 'Metode pembayaran wajib dipilih.';
    }

    $stmt = $pdo->prepare("SELECT * FROM workshops WHERE workshop_id = ? AND status_workshop = 'aktif'");
    $stmt->execute([$workshopId]);
    $workshop = $stmt->fetch();

    if (!$workshop) {
        $errors[] = 'Workshop tidak ditemukan atau sudah tidak aktif.';
    } else {
        // Cek kuota
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran_workshop WHERE workshop_id = ?");
        $stmt->execute([$workshopId]);
        $terdaftar = (int)$stmt->fetchColumn();

        if ($terdaftar >= $workshop['kapasitas_peserta']) {
            $errors[] = 'Kuota workshop sudah penuh.';
        }

        // Cek sudah daftar?
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran_workshop WHERE workshop_id = ? AND user_id_member = ?");
        $stmt->execute([$workshopId, $userId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Anda sudah terdaftar pada workshop ini.';
        }

        if (empty($errors) && $metode === 'credits') {
            $biaya = (int)$workshop['biaya_credits'];
            if ($saldo < $biaya) {
                $errors[] = 'Saldo credits tidak mencukupi untuk mendaftar workshop ini.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($metode === 'credits') {
                $biaya = (int)$workshop['biaya_credits'];
                $saldoBaru = $saldo - $biaya;
                $stmt = $pdo->prepare(
                    "INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit)
                     VALUES (?, ?, ?, 'keluar', ?)"
                );
                $stmt->execute([$userId, $biaya, $saldoBaru, 'Pendaftaran Workshop: ' . $workshop['judul_workshop']]);
                syncTierMember($userId, $saldoBaru);
                $saldo = $saldoBaru;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO pendaftaran_workshop (workshop_id, user_id_member, metode_bayar_workshop, status_kehadiran)
                 VALUES (?, ?, ?, 'belum')"
            );
            $stmt->execute([$workshopId, $userId, $metode]);

            $pdo->commit();
            $success = 'Pendaftaran workshop berhasil dikonfirmasi!';
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal mendaftar: ' . $ex->getMessage();
        }
    }
}

// Workshop mendatang dengan sisa slot
$stmt = $pdo->query(
    "SELECT w.*, u.nama_lengkap AS chef_nama,
            (SELECT COUNT(*) FROM pendaftaran_workshop p WHERE p.workshop_id = w.workshop_id) AS terdaftar
     FROM workshops w
     JOIN users u ON u.user_id = w.chef_pengampu_id
     WHERE w.status_workshop = 'aktif' AND w.tanggal_workshop >= CURDATE()
     ORDER BY w.tanggal_workshop ASC"
);
$workshopMendatang = $stmt->fetchAll();

// Workshop sudah diikuti
$stmt = $pdo->prepare(
    "SELECT w.*, u.nama_lengkap AS chef_nama, p.status_kehadiran, p.path_sertifikat
     FROM pendaftaran_workshop p
     JOIN workshops w ON w.workshop_id = p.workshop_id
     JOIN users u ON u.user_id = w.chef_pengampu_id
     WHERE p.user_id_member = ?
     ORDER BY w.tanggal_workshop DESC"
);
$stmt->execute([$userId]);
$workshopSaya = $stmt->fetchAll();

$pageTitle = 'Culinary Workshops';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .tab-pills { display: inline-flex; background: var(--color-surface-container-low); border-radius: var(--radius-full); padding: 4px; gap: 4px; }
    .tab-pill { padding: 10px 20px; border-radius: var(--radius-full); font-size: 13px; font-weight: 600; color: var(--color-on-surface-variant); }
    .tab-pill.active { background: var(--color-primary); color: var(--color-on-primary); }
    .featured-workshop {
        position: relative; border-radius: var(--radius-2xl); overflow: hidden;
        min-height: 320px; display: flex; align-items: flex-end;
        background: linear-gradient(to top, var(--color-surface) 10%, rgba(22,19,11,0.4)), var(--color-surface-container);
        border: 1px solid rgba(255,255,255,0.05);
        padding: var(--space-md);
        grid-column: span 2;
    }
    @media (max-width: 900px) { .featured-workshop { grid-column: span 1; } }
</style>

<header class="page-header">
    <div>
        <span class="eyebrow">Member Experience</span>
        <h2>Culinary Workshops</h2>
        <p>Reservasi tempat Anda di masterclass eksklusif yang dipimpin oleh Chef BistroFlow.</p>
    </div>
    <div class="tab-pills">
        <a href="?tab=upcoming" class="tab-pill <?= $tab === 'upcoming' ? 'active' : '' ?>">Upcoming Workshops</a>
        <a href="?tab=past" class="tab-pill <?= $tab === 'past' ? 'active' : '' ?>">Riwayat Saya</a>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($tab === 'upcoming'): ?>
    <?php if (empty($workshopMendatang)): ?>
        <div class="card empty-state">
            <span class="material-symbols-outlined icon">menu_book</span>
            <h4>Belum ada workshop mendatang</h4>
            <p>Pantau halaman ini untuk jadwal Culinary Storytelling Workshop terbaru.</p>
        </div>
    <?php else: ?>
    <div class="grid-3">
        <?php foreach ($workshopMendatang as $w):
            $sisaSlot = $w['kapasitas_peserta'] - $w['terdaftar'];
            $penuh = $sisaSlot <= 0;
        ?>
            <div class="catalog-card <?= $sisaSlot >= ($w['kapasitas_peserta'] * 0.7) ? 'featured-workshop' : '' ?>">
                <div class="thumb" style="height: 160px;"><span class="material-symbols-outlined" style="font-size:48px;">menu_book</span></div>
                <div class="body">
                    <div class="flex gap-sm" style="margin-bottom:8px;">
                        <span class="badge badge-info"><span class="material-symbols-outlined" style="font-size:14px;">calendar_today</span><?= date('d M Y', strtotime($w['tanggal_workshop'])) ?></span>
                        <span class="badge badge-neutral"><?= substr($w['waktu_mulai'], 0, 5) ?></span>
                    </div>
                    <h3><?= e($w['judul_workshop']) ?></h3>
                    <p class="meta"><span class="material-symbols-outlined" style="font-size:14px;">person</span> Chef <?= e($w['chef_nama']) ?></p>
                    <p style="font-size:13px; color: var(--color-on-surface-variant);"><?= e(mb_strimwidth($w['deskripsi_workshop'], 0, 100, '...')) ?></p>
                    <div class="footer-row">
                        <span class="font-mono" style="font-size:12px;">
                            <?php if ($penuh): ?>
                                <span class="text-error-color">Kuota Penuh</span>
                            <?php else: ?>
                                <strong style="color: var(--color-primary);"><?= $sisaSlot ?></strong> spots left
                            <?php endif; ?>
                        </span>
                        <?php if ($penuh): ?>
                            <button class="btn btn-secondary" disabled style="padding:8px 16px; font-size:13px;">Penuh</button>
                        <?php else: ?>
                            <button class="btn btn-primary" style="padding:8px 16px; font-size:13px;"
                                onclick='openWorkshopModal(<?= $w['workshop_id'] ?>, <?= json_encode($w['judul_workshop']) ?>, <?= (int)$w['biaya_credits'] ?>, <?= (float)$w['harga_uang'] ?>, <?= json_encode($w['metode_bayar_workshop']) ?>)'>
                                Daftar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (empty($workshopSaya)): ?>
        <div class="card empty-state">
            <span class="material-symbols-outlined icon">history</span>
            <h4>Belum ada riwayat workshop</h4>
            <p>Workshop yang Anda ikuti akan tampil di sini.</p>
        </div>
    <?php else: ?>
    <div class="grid-3">
        <?php foreach ($workshopSaya as $w): ?>
            <div class="catalog-card">
                <div class="thumb" style="height: 140px; <?= $w['status_kehadiran'] !== 'hadir' ? 'filter: grayscale(0.4); opacity:0.7;' : '' ?>">
                    <span class="material-symbols-outlined" style="font-size:48px;">menu_book</span>
                </div>
                <div class="body">
                    <div class="flex justify-between items-start">
                        <h3><?= e($w['judul_workshop']) ?></h3>
                        <?php
                        $kehadiranBadge = ['belum' => 'badge-warning', 'hadir' => 'badge-success', 'tidak_hadir' => 'badge-error'][$w['status_kehadiran']];
                        $kehadiranLabel = ['belum' => 'Menunggu', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak Hadir'][$w['status_kehadiran']];
                        ?>
                        <span class="badge <?= $kehadiranBadge ?>"><?= $kehadiranLabel ?></span>
                    </div>
                    <p class="meta">Chef <?= e($w['chef_nama']) ?> &middot; <?= date('d M Y', strtotime($w['tanggal_workshop'])) ?></p>
                    <div class="footer-row">
                        <?php if ($w['status_kehadiran'] === 'hadir' && $w['path_sertifikat']): ?>
                            <a href="<?= e($w['path_sertifikat']) ?>" class="btn btn-outline w-full" style="padding:8px; font-size:13px;" download>
                                <span class="material-symbols-outlined" style="font-size:16px;">download</span> Download Sertifikat
                            </a>
                        <?php elseif ($w['status_kehadiran'] === 'hadir'): ?>
                            <span class="form-help">Sertifikat sedang diproses</span>
                        <?php else: ?>
                            <span class="form-help">Sertifikat tersedia setelah kehadiran dikonfirmasi Admin</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal Pendaftaran Workshop -->
<div class="modal-overlay" id="workshop-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Form Pendaftaran Workshop</h3>
            <button class="modal-close" onclick="closeModal('workshop-modal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="daftar_workshop">
            <input type="hidden" name="workshop_id" id="modal-workshop-id">
            <div class="modal-body">
                <p style="margin-bottom:var(--space-md);">Workshop: <strong id="modal-workshop-title" style="color: var(--color-primary);"></strong></p>

                <div class="form-group">
                    <label>Metode Pembayaran <span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="metode_bayar" value="uang" required onchange="updateModalInfo()"> 
                            <span id="modal-harga-uang">Uang</span>
                        </label>
                        <label class="radio-option" id="modal-credits-option">
                            <input type="radio" name="metode_bayar" value="credits" onchange="updateModalInfo()"> 
                            <span id="modal-harga-credits">Credits</span>
                        </label>
                    </div>
                </div>
                <p class="form-help">Saldo credits Anda saat ini: <strong style="color: var(--color-primary);"><?= formatCredits($saldo) ?> pts</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('workshop-modal')">Batal</button>
                <button type="submit" class="btn btn-primary"><span class="btn-label">Konfirmasi Pendaftaran</span></button>
            </div>
        </form>
    </div>
</div>

<script>
let currentWorkshopSaldo = <?= $saldo ?>;

function openWorkshopModal(id, title, biayaCredits, hargaUang, metodeTersedia) {
    document.getElementById('modal-workshop-id').value = id;
    document.getElementById('modal-workshop-title').textContent = title;
    document.getElementById('modal-harga-uang').textContent = 'Uang (Rp' + hargaUang.toLocaleString('id-ID') + ')';
    document.getElementById('modal-harga-credits').textContent = 'Credits (' + biayaCredits.toLocaleString('id-ID') + ' pts)';

    const creditsOption = document.getElementById('modal-credits-option');
    if (metodeTersedia === 'uang') {
        creditsOption.style.display = 'none';
    } else {
        creditsOption.style.display = 'flex';
        if (biayaCredits > currentWorkshopSaldo) {
            creditsOption.querySelector('input').disabled = true;
            creditsOption.style.opacity = '0.5';
        }
    }
    if (metodeTersedia === 'credits') {
        document.querySelector('.radio-option:first-child').style.display = 'none';
    }
    openModal('workshop-modal');
}
function updateModalInfo() {}
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', () => openModal('workshop-modal'));
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
