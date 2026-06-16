<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pdo = getDB();
$errors = [];
$success = '';

// ============================================================
// PROSES KONFIGURASI ATURAN CREDITS (Modul 5: Form Konfigurasi Aturan Credits)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_konfigurasi') {
    $aturanNominal = (int)($_POST['aturan_credits_per_nominal'] ?? 0);
    $creditsKunjungan = (int)($_POST['credits_kunjungan'] ?? 0);
    $creditsWorkshop = (int)($_POST['credits_workshop'] ?? 0);

    if ($aturanNominal <= 0) {
        $errors[] = 'Aturan credits harus berupa angka positif.';
    }
    if ($creditsKunjungan <= 0) {
        $errors[] = 'Credits kunjungan harus berupa angka positif.';
    }
    if ($creditsWorkshop <= 0) {
        $errors[] = 'Credits workshop harus berupa angka positif.';
    }

    if (empty($errors)) {
        $stmt = $pdo->query("SELECT config_id FROM konfigurasi_credits ORDER BY config_id DESC LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $pdo->prepare("UPDATE konfigurasi_credits SET aturan_credits_per_nominal=?, credits_kunjungan=?, credits_workshop=? WHERE config_id=?");
            $stmt->execute([$aturanNominal, $creditsKunjungan, $creditsWorkshop, $row['config_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO konfigurasi_credits (aturan_credits_per_nominal, credits_kunjungan, credits_workshop) VALUES (?, ?, ?)");
            $stmt->execute([$aturanNominal, $creditsKunjungan, $creditsWorkshop]);
        }
        $success = 'Konfigurasi aturan credits berhasil disimpan.';
    }
}

$config = getKonfigurasiCredits();

// Statistik sistem untuk tampilan ringkas
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT role, COUNT(*) AS jumlah FROM users GROUP BY role");
$roleCounts = ['member' => 0, 'admin' => 0, 'chef' => 0, 'produsen' => 0];
foreach ($stmt->fetchAll() as $row) {
    $roleCounts[$row['role']] = (int)$row['jumlah'];
}

$pageTitle = 'System Settings';
$activeNav = 'settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>System Settings</h2>
        <p>Konfigurasi aturan konversi credits dari transaksi POS dan biaya penukaran fitur loyalty.</p>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card"><span class="label">Total Pengguna</span><div class="value light"><?= $totalUsers ?></div></div>
    <div class="stat-card"><span class="label">Member</span><div class="value"><?= $roleCounts['member'] ?></div></div>
    <div class="stat-card"><span class="label">Chef</span><div class="value light"><?= $roleCounts['chef'] ?></div></div>
    <div class="stat-card"><span class="label">Produsen</span><div class="value light"><?= $roleCounts['produsen'] ?></div></div>
</div>

<div class="card" style="max-width: 640px;">
    <h3 class="section-title">Form Konfigurasi Aturan Credits</h3>
    <form method="POST">
        <input type="hidden" name="action" value="simpan_konfigurasi">

        <div class="form-group">
            <label for="aturan_credits_per_nominal">Formula Konversi Nominal Transaksi POS <span class="required">*</span></label>
            <div class="flex items-center gap-sm">
                <span class="form-help">Rp</span>
                <input type="number" class="form-control" id="aturan_credits_per_nominal" name="aturan_credits_per_nominal" required min="1" value="<?= (int)$config['aturan_credits_per_nominal'] ?>">
                <span class="form-help">= 1 credit</span>
            </div>
            <p class="form-help">Contoh: Rp10.000 = 1 credit. Server PHP menghitung: credits = floor(nominal_transaksi / nilai ini)</p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="credits_kunjungan">Biaya Penukaran — Kunjungan Petani <span class="required">*</span></label>
                <input type="number" class="form-control" id="credits_kunjungan" name="credits_kunjungan" required min="1" value="<?= (int)$config['credits_kunjungan'] ?>">
                <p class="form-help">Jumlah credits yang dipotong untuk 1x reservasi kunjungan petani (Producer Visit Credits)</p>
            </div>
            <div class="form-group">
                <label for="credits_workshop">Biaya Penukaran — Workshop <span class="required">*</span></label>
                <input type="number" class="form-control" id="credits_workshop" name="credits_workshop" required min="1" value="<?= (int)$config['credits_workshop'] ?>">
                <p class="form-help">Jumlah credits yang dipotong untuk pendaftaran workshop via penukaran credits</p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><span class="btn-label">Simpan Konfigurasi</span></button>
        <p class="form-help" style="margin-top:8px;">Angka 0 tidak diperbolehkan untuk semua field konfigurasi credits.</p>
    </form>
</div>

<div class="card" style="max-width: 640px; margin-top: var(--space-md);">
    <h3 class="section-title">Threshold Tier Keanggotaan</h3>
    <p class="form-help" style="margin-bottom: var(--space-md);">Tier dihitung otomatis oleh PHP berdasarkan akumulasi saldo credits Member (syncTierMember).</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tier</th><th class="text-right">Minimum Saldo Credits</th><th>Akses Bahan Musiman</th></tr></thead>
            <tbody>
                <tr><td><span class="badge badge-tier-bronze">Bronze</span></td><td class="text-right">0</td><td class="text-muted">Akses umum</td></tr>
                <tr><td><span class="badge badge-tier-silver">Silver</span></td><td class="text-right">10.000</td><td class="text-muted">24 jam lebih awal</td></tr>
                <tr><td><span class="badge badge-tier-gold">Gold</span></td><td class="text-right">25.000</td><td class="text-muted">72 jam lebih awal</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
