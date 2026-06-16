<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['produsen', 'admin']);

$pdo = getDB();
$userId = $_SESSION['user_id'];
$search = trim($_GET['cari'] ?? '');

// Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bahan WHERE produsen_id = ?");
$stmt->execute([$userId]);
$totalProduk = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservasi r JOIN bahan b ON b.bahan_id = r.bahan_id WHERE b.produsen_id = ? AND r.status_reservasi = 'menunggu'");
$stmt->execute([$userId]);
$pesananTertunda = (int) $stmt->fetchColumn();

// Status kesehatan stok (tri-color)
$stmt = $pdo->prepare("SELECT status_ketersediaan, COUNT(*) AS jumlah FROM bahan WHERE produsen_id = ? GROUP BY status_ketersediaan");
$stmt->execute([$userId]);
$statusCounts = ['tersedia' => 0, 'terbatas' => 0, 'habis' => 0];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status_ketersediaan']] = (int) $row['jumlah'];
}
$totalBahanForBar = max(1, array_sum($statusCounts));
$persenTersedia = round($statusCounts['tersedia'] / $totalBahanForBar * 100);
$persenTerbatas = round($statusCounts['terbatas'] / $totalBahanForBar * 100);
$persenHabis = 100 - $persenTersedia - $persenTerbatas;

// Daftar bahan
$sql = "SELECT * FROM bahan WHERE produsen_id = ?";
$params = [$userId];
if ($search !== '') {
    $sql .= " AND nama_bahan LIKE ?";
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY nama_bahan ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bahanList = $stmt->fetchAll();

$pageTitle = 'Producer Dashboard';
$activeNav = 'produsen';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="page-header">
    <div>
        <h2>Producer Dashboard</h2>
        <p>Kelola pasokan bahan eksklusif premium, perbarui level stok, dan bagikan cerita kuliner di balik hasil panen
            Anda.</p>
    </div>
    <div class="flex gap-sm">
        <a href="<?= url('produsen/upload_bahan.php') ?>" class="btn btn-secondary">
            <span class="material-symbols-outlined" style="font-size:18px;">upload_file</span> Unggah Cerita Bahan Baru
        </a>
        <a href="<?= url('produsen/update_stok.php') ?>" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size:18px;">inventory_2</span> Update Stok
        </a>
    </div>
</header>

<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Total Produk Aktif</span>
            <span class="material-symbols-outlined" style="color: var(--color-primary);">eco</span>
        </div>
        <div class="value"><?= $totalProduk ?></div>
    </div>
    <div class="stat-card">
        <div class="flex justify-between items-center">
            <span class="label">Pesanan Tertunda</span>
            <span class="material-symbols-outlined"
                style="color: var(--color-on-surface-variant);">shopping_cart_checkout</span>
        </div>
        <div class="value light"><?= $pesananTertunda ?></div>
        <p class="form-help">Menunggu konfirmasi Admin</p>
    </div>
    <div class="stat-card">
        <span class="label">Status Kesehatan Stok</span>
        <p style="font-size:16px; margin: 4px 0 8px;">
            <?php
            if ($persenHabis > 30)
                echo 'Beberapa stok perlu perhatian.';
            elseif ($persenTerbatas > 30)
                echo 'Sebagian stok terbatas.';
            else
                echo 'Sebagian besar stok aman.';
            ?>
        </p>
        <div class="progress-bar" style="display:flex;">
            <div style="height:100%; background: var(--color-primary); width: <?= $persenTersedia ?>%;"></div>
            <div style="height:100%; background: var(--color-secondary-container); width: <?= $persenTerbatas ?>%;">
            </div>
            <div style="height:100%; background: var(--color-error-container); width: <?= $persenHabis ?>%;"></div>
        </div>
        <div class="flex justify-between font-mono"
            style="font-size:11px; color: var(--color-on-surface-variant); margin-top:6px;">
            <span>Tersedia</span><span>Terbatas</span><span>Habis</span>
        </div>
    </div>
</div>

<div class="flex justify-between items-center mb-md">
    <h3 class="section-title" style="margin-bottom:0;">Daftar Bahan Baku</h3>
    <form method="GET" class="flex gap-sm">
        <input type="text" name="cari" class="form-control" placeholder="Cari bahan..." value="<?= e($search) ?>"
            style="width:240px;">
        <button type="submit" class="btn btn-secondary"><span class="material-symbols-outlined"
                style="font-size:18px;">search</span></button>
    </form>
</div>

<?php if (empty($bahanList)): ?>
    <div class="card empty-state">
        <span class="material-symbols-outlined icon">inventory_2</span>
        <h4>Belum ada bahan terdaftar</h4>
        <p>Unggah cerita bahan baru untuk mulai mengisi katalog Anda.</p>
    </div>
<?php else: ?>
    <div class="table-wrap table-wrap-scroll">
        <table>
            <thead>
                <tr>
                    <th>Nama Bahan</th>
                    <th>Asal Daerah</th>
                    <th class="text-right">Kuota / Stok</th>
                    <th class="text-right">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bahanList as $b):
                    $statusBadge = ['tersedia' => 'badge-success', 'terbatas' => 'badge-warning', 'habis' => 'badge-error'][$b['status_ketersediaan']];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= e($b['nama_bahan']) ?></div>
                            <div class="form-help">ID: BHN-<?= str_pad($b['bahan_id'], 3, '0', STR_PAD_LEFT) ?></div>
                        </td>
                        <td class="text-muted"><?= e($b['asal_daerah']) ?></td>
                        <td class="text-right"><?= $b['kuota_reservasi'] ?>         <?= $b['is_musiman'] ? 'slot' : 'unit' ?></td>
                        <td class="text-right"><span
                                class="badge <?= $statusBadge ?>"><?= e(ucfirst($b['status_ketersediaan'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>