<?php
$role = currentRole();

// PERBAIKAN: Jika halaman utama memiliki variabel query $user, pakai data real-time database. 
// Jika tidak ada, baru gunakan fallback fungsi session bawaan.
$tier = isset($user['tier_member']) ? $user['tier_member'] : currentTier();

$activeNav = $activeNav ?? '';

// BASE URL WAJIB (karena di subfolder bistroflow)
$base = '/bistroflow/';

$navItems = [
    // ==========================================
    // MENU ROLE NON-ADMIN (Eksklusif per role)
    // ==========================================
    'member' => [
        'label' => 'Member Portal',
        'icon' => 'person',
        'href' => $base . 'member/dashboard.php',
        'roles' => ['member']
    ],

    'chef' => [
        'label' => "Chef's Table",
        'icon' => 'restaurant',
        'href' => $base . 'chef/dashboard.php',
        'roles' => ['chef']
    ],

    'produsen' => [
        'label' => 'Producer Hub',
        'icon' => 'local_shipping',
        'href' => $base . 'produsen/dashboard.php',
        'roles' => ['produsen']
    ],

    // ==========================================
    // MENU UTAMA ADMIN & MANAJEMEN WORKSHOP / MEMBER
    // ==========================================
    'admin' => [
        'label' => 'Admin Control',
        'icon' => 'admin_panel_settings',
        'href' => $base . 'admin/dashboard.php',
        'roles' => ['admin']
    ],

    'kelola_workshop' => [
        'label' => 'Kelola Workshop',
        'icon' => 'analytics',
        'href' => $base . 'admin/kelola_workshop.php',
        'roles' => ['admin']
    ],

    'kelola_member' => [
        'label' => 'Kelola Member',
        'icon' => 'group',
        'href' => $base . 'admin/kelola_member.php',
        'roles' => ['admin']
    ],

    // ==========================================
    // PENAMBAHAN MENU BARU KHUSUS ADMIN
    // ==========================================
    'laporan_pos' => [
        'label' => 'Laporan POS',
        'icon' => 'receipt',
        'href' => $base . 'admin/laporan_pos.php',
        'roles' => ['admin']
    ],

    'kelola_bahan' => [
        'label' => 'Kelola Bahan',
        'icon' => 'eco',
        'href' => $base . 'admin/kelola_bahan.php',
        'roles' => ['admin']
    ],

    'loyalty' => [
        'label' => 'Loyalty & Credits',
        'icon' => 'loyalty',
        'href' => $base . 'member/loyalty.php',
        'roles' => ['member', 'admin']
    ],

    'storytelling' => [
        'label' => 'Story Telling',
        'icon' => 'auto_stories',
        'href' => $base . 'member/storytelling.php',
        'roles' => ['member', 'admin']
    ],

    'reservasi' => [
        'label' => 'Reservasi Bahan',
        'icon'  => 'eco',
        'href'  => $base . 'member/reservasi.php',
        'roles' => ['member', 'admin']
    ],

    'workshop' => [
        'label' => 'Culinary Workshops',
        'icon'  => 'school',
        'href'  => $base . 'member/workshop.php',
        'roles' => ['member', 'admin']
    ],

    // ==========================================
    // BARU: MENU PROFIL SAYA (Bisa Diakses Semua Role)
    // ==========================================
    'profil' => [
        'label' => 'Profil Saya',
        'icon' => 'account_circle',
        'href' => $base . $role . '/profil.php',
        'roles' => ['member', 'chef', 'produsen', 'admin']
    ],

    // ==========================================
    // SYSTEM SETTINGS
    // ==========================================
    'settings' => [
        'label' => 'System Settings',
        'icon' => 'settings',
        'href' => $base . 'admin/konfigurasi.php',
        'roles' => ['admin']
    ],
];

// Deteksi otomatis nama script agar menu menyala dengan benar (Active State Fix)
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if ($currentScript === 'kelola_workshop.php') {
    $activeNav = 'kelola_workshop';
} elseif ($currentScript === 'kelola_member.php') {
    $activeNav = 'kelola_member';
} elseif ($currentScript === 'laporan_pos.php') {
    $activeNav = 'laporan_pos';
} elseif ($currentScript === 'review_konten.php') {
    $activeNav = 'review_konten';
} elseif ($currentScript === 'kelola_bahan.php') {
    $activeNav = 'kelola_bahan';
} elseif ($currentScript === 'profil.php') {
    $activeNav = 'profil';
} elseif ($currentScript === 'konfigurasi.php') {
    $activeNav = 'settings';
}
?>

<div class="app-shell">
    <nav class="sidebar">

        <div class="sidebar-header">
            <div class="sidebar-logo">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">
                    restaurant
                </span>
            </div>

            <div class="sidebar-brand">
                <h1>BistroFlow ERP</h1>
                <p>Culinary Management</p>
            </div>
        </div>

        <?php if ($role): ?>
            <button class="sidebar-cta" type="button"
                onclick="window.location.href='<?= e($navItems[$role]['href'] ?? $base . 'member/dashboard.php') ?>'">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                New Entry
            </button>
        <?php endif; ?>

        <div class="sidebar-nav">
            <?php foreach ($navItems as $key => $item): ?>
                <?php if (in_array($role, $item['roles'], true)): ?>
                    <a href="<?= e($item['href']) ?>" class="nav-item <?= $activeNav === $key ? 'active' : '' ?>">
                        <span class="material-symbols-outlined icon">
                            <?= e($item['icon']) ?>
                        </span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer">
            <?php if ($role === 'member' && $tier): ?>
                <div class="tier-badge-box">
                    <div>
                        <p class="label">Current Tier</p>
                        <p class="value tier-<?= e($tier) ?>">
                            <?= e(ucfirst($tier)) ?> Member
                        </p>
                    </div>
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">
                        workspace_premium
                    </span>
                </div>
            <?php endif; ?>

            <a href="#" class="nav-item">
                <span class="material-symbols-outlined icon">help</span>
                <span>Help Center</span>
            </a>

            <a href="<?= url('auth/logout.php') ?>" class="nav-item" style="color: var(--color-error);">
                <span class="material-symbols-outlined icon">logout</span>
                <span>Sign Out</span>
            </a>
        </div>
    </nav>
    <main class="main-content">