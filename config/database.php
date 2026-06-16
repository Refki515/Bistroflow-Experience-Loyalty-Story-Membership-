<?php
/**
 * BistroFlow ERP - Konfigurasi Database
 * Koneksi MySQL menggunakan PDO dengan prepared statement
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'bistroflow_erp');
define('DB_USER', 'root');
define('DB_PASS', 'refki2006');
define('DB_CHARSET', 'utf8mb4');

/**
 * BASE_PATH: prefix path jika aplikasi diletakkan di subfolder
 * (contoh: XAMPP/Laragon -> htdocs/bistroflow, akses via localhost/bistroflow/)
 * Kosongkan ('') jika aplikasi berada di document root langsung.
 */
define('BASE_PATH', '/bistroflow');

// Direktori upload file (relatif terhadap root aplikasi)
define('UPLOAD_DIR_KONTEN', __DIR__ . '/../uploads/konten/');
define('UPLOAD_DIR_SERTIFIKAT', __DIR__ . '/../uploads/sertifikat/');
define('UPLOAD_DIR_REKAMAN', __DIR__ . '/../uploads/rekaman/');
define('UPLOAD_DIR_BAHAN', __DIR__ . '/../uploads/bahan/');

define('UPLOAD_URL_KONTEN', BASE_PATH . '/uploads/konten/');
define('UPLOAD_URL_SERTIFIKAT', BASE_PATH . '/uploads/sertifikat/');
define('UPLOAD_URL_REKAMAN', BASE_PATH . '/uploads/rekaman/');
define('UPLOAD_URL_BAHAN', BASE_PATH . '/uploads/bahan/');
// Batas ukuran file upload (sesuai Modul 5: maks. 50MB)
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'mp4']);

/**
 * Membuat koneksi PDO ke MySQL
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            die('Koneksi database gagal. Silakan coba beberapa saat lagi.');
        }
    }

    return $pdo;
}