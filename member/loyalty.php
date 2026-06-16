<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['member', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$config = getKonfigurasiCredits();
$saldo = getSaldoCredits($userId);
$progress = getTierProgress($saldo);

$errors = [];
$success = '';

// ============================================================
// PROSES PENUKARAN CREDITS (Modul 5: Form Penukaran Credits)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tukar_credits') {
    $jenis = $_POST['jenis_penukaran'] ?? '';
    $jumlahPeserta = max(1, (int)($_POST['jumlah_peserta'] ?? 1));
    $tanggalReservasi = $_POST['tanggal_reservasi'] ?? '';
    $workshopId = (int)($_POST['workshop_id'] ?? 0);

    if (!in_array($jenis, ['kunjungan_petani', 'workshop'], true)) {
        $errors[] = 'Jenis penukaran wajib dipilih.';
    }

    $creditsDibutuhkan = $jenis === 'kunjungan_petani'
        ? (int)$config['credits_kunjungan']
        : (int)$config['credits_workshop'];

    if (empty($errors)) {
        if ($saldo < $creditsDibutuhkan) {
            $errors[] = 'Saldo credits tidak mencukupi untuk penukaran ini.';
        } 
        // FIX: Validasi tanggal hanya dilakukan jika memilih jenis kunjungan_petani
        elseif ($jenis === 'kunjungan_petani' && ($tanggalReservasi === '' || strtotime($tanggalReservasi) < strtotime('today'))) {
            $errors[] = 'Tanggal reservasi tidak valid atau sudah lewat.';
        } else {
            try {
                $pdo->beginTransaction();

                // Kurangi saldo credits (atomik)
                $saldoBaru = $saldo - $creditsDibutuhkan;
                $stmt = $pdo->prepare(
                    "INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit)
                     VALUES (?, ?, ?, 'keluar', ?)"
                );
                $keterangan = $jenis === 'kunjungan_petani'
                    ? 'Penukaran credits — Kunjungan Petani'
                    : 'Penukaran credits — Pendaftaran Workshop';
                $stmt->execute([$userId, $creditsDibutuhkan, $saldoBaru, $keterangan]);

                // Insert reservasi (jenis kunjungan_petani)
                if ($jenis === 'kunjungan_petani') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO reservasi (user_id_member, jenis_reservasi, tanggal_reservasi, jumlah_peserta, status_reservasi, credits_digunakan)
                         VALUES (?, 'kunjungan_petani', ?, ?, 'menunggu', ?)"
                    );
                    $stmt->execute([$userId, $tanggalReservasi, $jumlahPeserta, $creditsDibutuhkan]);
                } else {
                    // Daftar workshop via credits
                    if ($workshopId <= 0) {
                        throw new RuntimeException('Workshop wajib dipilih.');
                    }
                    $stmt = $pdo->prepare(
                        "INSERT INTO pendaftaran_workshop (workshop_id, user_id_member, metode_bayar_workshop, status_kehadiran)
                         VALUES (?, ?, 'credits', 'belum')"
                    );
                    $stmt->execute([$workshopId, $userId]);
                }

                $pdo->commit();
                syncTierMember($userId, $saldoBaru);
                $success = 'Penukaran credits berhasil! Reservasi telah dibuat dan menunggu konfirmasi Admin.';
                $saldo = $saldoBaru;
                $progress = getTierProgress($saldo);
            } catch (Exception $ex) {
                $pdo->rollBack();
                $errors[] = 'Gagal memproses penukaran: ' . $ex->getMessage();
            }
        }
    }
}

// Riwayat credits lengkap dengan pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM credits WHERE user_id = ?");
$stmt->execute([$userId]);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT * FROM credits WHERE user_id = ? ORDER BY tanggal_credit DESC LIMIT $perPage OFFSET $offset");
$stmt->execute([$userId]);
$riwayat = $stmt->fetchAll();

// Workshop aktif untuk dropdown penukaran
$stmt = $pdo->query("SELECT * FROM workshops WHERE status_workshop = 'aktif' AND tanggal_workshop >= CURDATE() ORDER BY tanggal_workshop ASC");
$workshopAktif = $stmt->fetchAll();

$pageTitle = 'Loyalty & Credits';
$activeNav = 'member';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Member Wallet</span>
        <h2>Loyalty &amp; Credits</h2>
        <p>Pantau saldo credits, riwayat transaksi, dan tukar credits untuk kunjungan petani atau workshop kuliner.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<!-- Saldo Credits -->
<div class="glass-panel" style="padding: var(--space-md); margin-bottom: var(--space-md);">
    <div class="flex justify-between items-center" style="flex-wrap: wrap; gap: var(--space-md);">
        <div style="flex:1; min-width:280px;">
            <p class="font-mono" style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color: var(--color-on-surface-variant); margin-bottom:8px;">Member Wallet</p>
            <p style="font-size:18px; margin-bottom:4px;">Saldo Credits</p>
            <div class="flex items-center gap-sm" style="margin-bottom: var(--space-md);">
                <span style="font-size:48px; font-weight:700; color: var(--color-primary);"><?= formatCredits($saldo) ?></span>
                <span style="color: var(--color-on-surface-variant);">Pts</span>
            </div>
            <?php if ($progress['next']): ?>
            <div style="max-width: 420px;">
                <div class="flex justify-between font-mono" style="font-size:12px; margin-bottom:8px;">
                    <span style="color: var(--color-primary);"><?= e(ucfirst($progress['current'])) ?> Tier</span>
                    <span class="text-muted"><?= e(ucfirst($progress['next'])) ?> at <?= formatCredits($progress['next_threshold']) ?></span>
                </div>
                <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $progress['percent'] ?>%;"></div></div>
                <p class="form-help" style="margin-top:8px;"><?= formatCredits($progress['next_threshold'] - $saldo) ?> Credits remaining to unlock <?= e(ucfirst($progress['next'])) ?> privileges.</p>
            </div>
            <?php endif; ?>
        </div>
        <button class="btn btn-primary" style="padding: 14px 32px; font-size:15px;" onclick="openModal('redeem-modal')" <?= $saldo <= 0 ? 'disabled' : '' ?>>
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">redeem</span>
            Tukar Credits
        </button>
    </div>
    <?php if ($saldo <= 0): ?>
        <p class="form-help" style="margin-top:8px;">Saldo credits Anda 0. Lakukan transaksi di restoran untuk mendapat credits.</p>
    <?php endif; ?>
</div>

<!-- Tabel Riwayat -->
<div class="flex justify-between items-center mb-md">
    <h3 class="section-title" style="margin-bottom:0;">Tabel Riwayat Credits</h3>
</div>

<?php if (empty($riwayat)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">receipt_long</span>
        <h4>Belum ada transaksi credits</h4>
        <p>Lakukan transaksi di restoran untuk mulai mengumpulkan credits.</p>
    </div>
<?php else: ?>
<div class="table-wrap table-wrap-scroll">
    <table>
        <thead>
            <tr><th>Tanggal</th><th>Deskripsi Transaksi</th><th class="text-right">Jumlah</th><th class="text-center">Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($riwayat as $c): ?>
            <tr>
                <td class="text-muted"><?= date('d M Y', strtotime($c['tanggal_credit'])) ?></td>
                <td><?= e($c['keterangan_credit'] ?: '-') ?></td>
                <td class="text-right <?= $c['jenis_transaksi_credit'] === 'masuk' ? 'text-primary-color' : 'text-error-color' ?>">
                    <?= $c['jenis_transaksi_credit'] === 'masuk' ? '+' : '-' ?><?= formatCredits($c['jumlah_credits']) ?>
                </td>
                <td class="text-center">
                    <?php if ($c['jenis_transaksi_credit'] === 'masuk'): ?>
                        <span class="badge badge-success">Earned</span>
                    <?php else: ?>
                        <span class="badge badge-error">Redeemed</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Modal Tukar Credits -->
<div class="modal-overlay" id="redeem-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Tukar Credits</h3>
            <button class="modal-close" onclick="closeModal('redeem-modal')">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" id="redeemForm">
            <input type="hidden" name="action" value="tukar_credits">
            <div class="modal-body">
                <p style="margin-bottom: var(--space-md);">Saldo credits saat ini: <strong style="color: var(--color-primary);"><?= formatCredits($saldo) ?> Pts</strong></p>

                <div class="form-group">
                    <label for="jenis_penukaran">Jenis Penukaran <span class="required">*</span></label>
                    <select class="form-control" id="jenis_penukaran" name="jenis_penukaran" required onchange="toggleRedeemFields()">
                        <option value="">-- Pilih jenis --</option>
                        <option value="kunjungan_petani">Kunjungan Petani (<?= formatCredits($config['credits_kunjungan']) ?> pts)</option>
                        <option value="workshop">Workshop Kuliner (<?= formatCredits($config['credits_workshop']) ?> pts)</option>
                    </select>
                </div>

                <div class="form-group" id="field-workshop" style="display:none;">
                    <label for="workshop_id">Pilih Workshop <span class="required">*</span></label>
                    <select class="form-control" id="workshop_id" name="workshop_id">
                        <option value="">-- Pilih slot tersedia --</option>
                        <?php foreach ($workshopAktif as $w): ?>
                            <option value="<?= $w['workshop_id'] ?>"><?= e($w['judul_workshop']) ?> — <?= date('d M Y', strtotime($w['tanggal_workshop'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="field-kunjungan" style="display:none;">
                    <label for="tanggal_reservasi">Tanggal Kunjungan <span class="required">*</span></label>
                    <!-- FIX: Set default minimal H+1 dari hari ini agar secara HTML tidak melanggar aturan logika di PHP -->
                    <input type="date" class="form-control" id="tanggal_reservasi" name="tanggal_reservasi" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    <p class="form-help">Format: YYYY-MM-DD, tidak boleh di masa lalu</p>

                    <label for="jumlah_peserta" style="margin-top: var(--space-md);">Jumlah Peserta <span class="required">*</span></label>
                    <input type="number" class="form-control" id="jumlah_peserta" name="jumlah_peserta" min="1" max="20" value="1">
                </div>

                <p class="form-help">Tidak boleh melebihi saldo yang tersedia. PHP akan memvalidasi ulang sebelum memproses.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('redeem-modal')">Batal</button>
                <button type="submit" class="btn btn-primary" id="redeemSubmit">
                    <span class="btn-label">Tukar Credits</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRedeemFields() {
    const jenis = document.getElementById('jenis_penukaran').value;
    document.getElementById('field-workshop').style.display = jenis === 'workshop' ? 'block' : 'none';
    document.getElementById('field-kunjungan').style.display = jenis === 'kunjungan_petani' ? 'block' : 'none';
    document.getElementById('workshop_id').required = (jenis === 'workshop');
    document.getElementById('tanggal_reservasi').required = (jenis === 'kunjungan_petani');
}
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', () => openModal('redeem-modal'));
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>