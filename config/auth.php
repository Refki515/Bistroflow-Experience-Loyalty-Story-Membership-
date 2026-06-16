<?php
/**
 * BistroFlow ERP - Konfigurasi Session & Helper Autentikasi
 * Autentikasi menggunakan session PHP + bcrypt (password_hash/password_verify)
 * Konsisten dengan Modul 3 (arsitektur), Modul 5 (form login), Modul 9 (skema users)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Cek apakah pengguna sudah login
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Ambil role pengguna yang sedang login
 */
function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Ambil tier member yang sedang login
 */
function currentTier(): ?string
{
    return $_SESSION['tier_member'] ?? null;
}

/**
 * Wajibkan login. Jika tidak, redirect ke halaman login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
         header('Location: ' . url('auth/login.php'));
        exit;
    }
}

/**
 * Wajibkan role tertentu. Jika tidak sesuai, tolak akses.
 * @param string|array $roles Role yang diizinkan
 */
function requireRole($roles): void
{
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        echo 'Akses ditolak. Halaman ini hanya untuk role: ' . implode(', ', $roles);
        exit;
    }
}

/**
 * Buat session setelah login berhasil
 */
function createUserSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['tier_member'] = $user['tier_member'];
    $_SESSION['login_time']  = time();
}

/**
 * Hapus session (logout)
 */
function destroySession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Redirect helper berdasarkan role setelah login
 */
function redirectByRole(string $role): void
{
    switch ($role) {
        case 'admin':
            header('Location: ' . url('/admin/dashboard.php'));
            break;
        case 'chef':
            header('Location: ' . url('/chef/dashboard.php'));
            break;
        case 'produsen':
            header('Location: ' . url('/produsen/dashboard.php'));
            break;
        default:
            header('Location: ' . url('/member/dashboard.php'));
            break;
    }
    exit;
}

/**
 * Sanitasi output untuk mencegah XSS
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka credits dengan pemisah ribuan
 */
function formatCredits(int $value): string
{
    return number_format($value, 0, ',', '.');
}

/**
 * Format rupiah
 */
function formatRupiah($value): string
{
    return 'Rp' . number_format((float)$value, 0, ',', '.');
}

/**
 * Bentuk URL absolute dengan prefix BASE_PATH (untuk instalasi di subfolder,
 * misal XAMPP/Laragon htdocs/bistroflow -> diakses via localhost/bistroflow/)
 */
function url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return rtrim(BASE_PATH, '/') . $path;
}