<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirectByRole(currentRole());
}

// Ambil 3 konten tayang untuk preview katalog (Modul 6: status_konten = tayang)
$pdo = getDB();
$stmt = $pdo->query(
    "SELECT k.*, u.nama_lengkap AS pembuat FROM konten k
     JOIN users u ON u.user_id = k.pembuat_konten_id
     WHERE k.status_konten = 'tayang'
     ORDER BY k.tanggal_upload DESC LIMIT 3"
);
$previewKonten = $stmt->fetchAll();

$pageTitle = 'Kuliner dengan Cerita';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    body {
        overflow-x: hidden;
    }

    .lp-nav {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 50;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 var(--space-lg);
        height: 64px;
        background-color: rgba(22, 19, 11, 0.8);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(77, 70, 53, 0.2);
    }

    .lp-nav .brand {
        font-size: 20px;
        font-weight: 700;
        color: var(--color-primary);
    }

    /* MODIFIKASI: Menambahkan gambar latar belakang dengan overlay gelap */
    .lp-hero {
        position: relative;
        min-height: 720px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 120px var(--space-lg) var(--space-xl);

        /* Ganti 'assets/img/hero-chef.jpg' dengan path/url gambar dapur/chef Anda */
        background:
            linear-gradient(to bottom, rgba(20, 17, 10, 0.4), rgba(20, 17, 10, 1.2)),
            url('assets/image/hero.png') no-repeat center center;
        background-size: cover;
    }

    .lp-hero .eyebrow {
        font-family: var(--font-mono);
        font-size: 12px;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--color-primary);
        padding: 6px 16px;
        border-radius: var(--radius-full);
        background: rgba(45, 42, 33, 0.7);
        border: 1px solid rgba(242, 202, 80, 0.2);
        margin-bottom: var(--space-md);
        z-index: 2;
    }

    .lp-hero h1 {
        font-size: 56px;
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: var(--space-md);
        max-width: 800px;
        z-index: 2;
    }

    .lp-hero p {
        color: #e1dacb;
        font-size: 18px;
        max-width: 640px;
        margin-bottom: var(--space-md);
        z-index: 2;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }

    .lp-hero .btn {
        z-index: 2;
    }

    .lp-section {
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-xl) var(--space-lg);
    }

    .lp-section h2 {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .lp-section .lead {
        color: var(--color-on-surface-variant);
        margin-bottom: var(--space-lg);
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-md);
        grid-auto-rows: 220px;
    }

    .feature-card {
        border-radius: var(--radius-2xl);
        border: 1px solid rgba(77, 70, 53, 0.4);
        background-color: var(--color-surface-container-low);
        padding: var(--space-md);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.3s;
    }

    .feature-card:hover {
        border-color: rgba(242, 202, 80, 0.4);
    }

    .feature-card .material-symbols-outlined {
        font-size: 28px;
        color: var(--color-primary);
    }

    .feature-card.large {
        grid-column: span 2;
        grid-row: span 2;
    }

    .feature-card h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 12px 0 6px;
    }

    .feature-card p {
        font-size: 14px;
        color: var(--color-on-surface-variant);
    }

    @media (max-width: 900px) {
        .feature-grid {
            grid-template-columns: 1fr 1fr;
        }

        .feature-card.large {
            grid-column: span 2;
            grid-row: span 1;
        }

        .lp-hero h1 {
            font-size: 36px;
        }
    }

    .lp-footer {
        border-top: 1px solid rgba(77, 70, 53, 0.2);
        padding: var(--space-lg);
        text-align: center;
        color: var(--color-on-surface-variant);
        font-size: 13px;
    }

    .lp-footer .links {
        display: flex;
        justify-content: center;
        gap: var(--space-md);
        margin-bottom: 12px;
    }

    .lp-footer .links a:hover {
        color: var(--color-primary);
    }
</style>

<nav class="lp-nav">
    <div class="brand">BistroFlow</div>
    <a href="<?= url('auth/login.php') ?>" class="btn btn-outline">Login</a>
</nav>

<header class="lp-hero">
    <span class="eyebrow">Elevating Culinary Management</span>
    <h1>Kuliner dengan Cerita</h1>
    <p>BistroFlow ERP mengubah pengalaman kuliner dari sekadar transaksi makanan menjadi perjalanan naratif yang
        mendalam — jejak bahan dari sumber hingga piring, dan pilihan etis yang terukur.</p>
    <a href="<?= url('auth/login.php?mode=register') ?>" class="btn btn-primary"
        style="padding: 16px 32px; font-size:16px;">
        Explore BistroFlow
    </a>
</header>
<section class="lp-section">
    <h2>Exclusive Offerings</h2>
    <p class="lead">Lima fitur utama modul Experience Loyalty &amp; Story Membership.</p>
    <div class="feature-grid">
        <div class="feature-card large"
            style="background-image: linear-gradient(rgba(0, 0, 0, 0), rgba(0, 0, 0, 2)), url('assets/image/card1.png'); background-size: cover; background-position: center;">
            <span class="material-symbols-outlined">explore</span>
            <div>
                <h3>Ingredient Adventure Club</h3>
                <p>Membership dengan akses ke bahan eksklusif lengkap dengan cerita asal-usul, lokasi, dan proses
                    produksinya.</p>
            </div>
        </div>
        <div class="feature-card">
            <span class="material-symbols-outlined">local_shipping</span>
            <div>
                <h3>Producer Visit Credits</h3>
                <p>Poin loyalitas dari transaksi POS dapat ditukar dengan kunjungan ke petani dan produsen mitra.</p>
            </div>
        </div>
        <div class="feature-card">
            <span class="material-symbols-outlined">movie</span>
            <div>
                <h3>Story Behind-the-Scenes</h3>
                <p>Akses konten eksklusif tentang pencarian bahan dan pengembangan menu oleh Chef dan Produsen.</p>
            </div>
        </div>
        <div class="feature-card">
            <span class="material-symbols-outlined">event_seat</span>
            <div>
                <h3>Seasonal First Access</h3>
                <p>Prioritas akses ke bahan musiman terbatas — Gold 72 jam dan Silver 24 jam lebih awal dari Member
                    lain.</p>
            </div>
        </div>
        <div class="feature-card">
            <span class="material-symbols-outlined">menu_book</span>
            <div>
                <h3>Culinary Storytelling Workshops</h3>
                <p>Workshop bersama Chef untuk memahami cara membaca dan menikmati cerita di balik setiap hidangan.</p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($previewKonten)): ?>
    <section class="lp-section">
        <h2>Katalog Cerita Kuliner</h2>
        <p class="lead">Pratinjau konten storytelling yang sudah tayang.</p>
        <div class="grid-3">
            <?php foreach ($previewKonten as $k): ?>
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
                        <span class="badge badge-warning"
                            style="align-self:flex-start;"><?= e(ucfirst($k['jenis_konten'])) ?></span>
                        <h3><?= e($k['judul_konten']) ?></h3>
                        <p class="meta"><?= e($k['pembuat']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<footer class="lp-footer">
    <div class="links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
    </div>
    <div>&copy; 2026 BistroFlow ERP — Kuliner dengan Cerita</div>
</footer>
</body>

</html>