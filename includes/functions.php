<?php
/**
 * BistroFlow ERP - Bootstrap
 * Memuat konfigurasi database dan session/auth.
 * Wajib di-include di awal setiap halaman PHP.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

/**
 * Ambil konfigurasi credits aktif dari MySQL (Modul 9: tabel konfigurasi_credits)
 */
function getKonfigurasiCredits(): array
{
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM konfigurasi_credits ORDER BY config_id DESC LIMIT 1");
    $row = $stmt->fetch();
    return $row ?: [
        'aturan_credits_per_nominal' => 10000,
        'credits_kunjungan' => 500,
        'credits_workshop' => 500,
    ];
}

/**
 * Ambil saldo credits terbaru milik user
 */
function getSaldoCredits(int $userId): int
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT saldo_credits FROM credits WHERE user_id = ? ORDER BY credit_id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['saldo_credits'] : 0;
}

/**
 * Hitung tier berikutnya berdasarkan saldo credits.
 * Threshold disesuaikan dengan kebutuhan Producer Visit Credits & Seasonal First Access.
 */
function getTierProgress(int $saldo): array
{
    $thresholds = [
        'bronze' => 0,
        'silver' => 10000,
        'gold'   => 25000,
    ];

    if ($saldo >= $thresholds['gold']) {
        return ['current' => 'gold', 'next' => null, 'next_threshold' => null, 'percent' => 100];
    } elseif ($saldo >= $thresholds['silver']) {
        $percent = (int)(($saldo - $thresholds['silver']) / ($thresholds['gold'] - $thresholds['silver']) * 100);
        return ['current' => 'silver', 'next' => 'gold', 'next_threshold' => $thresholds['gold'], 'percent' => max(0, min(100, $percent))];
    } else {
        $percent = (int)($saldo / $thresholds['silver'] * 100);
        return ['current' => 'bronze', 'next' => 'silver', 'next_threshold' => $thresholds['silver'], 'percent' => max(0, min(100, $percent))];
    }
}

/**
 * Update tier_member di tabel users jika saldo credits berubah tier-nya.
 * (denormalisasi terkontrol sesuai Modul 9 - bagian Normalisasi 3NF)
 */
function syncTierMember(int $userId, int $saldo): void
{
    $pdo = getDB();
    $progress = getTierProgress($saldo);
    $stmt = $pdo->prepare("UPDATE users SET tier_member = ? WHERE user_id = ?");
    $stmt->execute([$progress['current'], $userId]);

    // Update session jika user yang login adalah user ini
    if (isLoggedIn() && $_SESSION['user_id'] === $userId) {
        $_SESSION['tier_member'] = $progress['current'];
    }
}

/**
 * Buat notifikasi baru untuk seorang pengguna (Modul 8/9: tabel notifikasi)
 */
function createNotification(int $penerimaId, string $jenis, string $isi, ?string $tierTarget = null): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO notifikasi (penerima_id, jenis_notifikasi, isi_notifikasi, status_pengiriman, tier_target)
         VALUES (?, ?, ?, 'belum_dibaca', ?)"
    );
    $stmt->execute([$penerimaId, $jenis, $isi, $tierTarget]);
}

/**
 * Validasi dan simpan file upload ke direktori server (move_uploaded_file)
 * Konsisten dengan Modul 5: format jpg/png/mp4, maks 50MB.
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function handleFileUpload(array $file, string $targetDir, string $targetUrl): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => null, 'error' => 'File wajib diunggah.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Terjadi kesalahan saat mengunggah file.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'path' => null, 'error' => 'Ukuran file melebihi batas 50MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Format file tidak didukung. Gunakan jpg, png, atau mp4.'];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = uniqid('bf_', true) . '.' . $ext;
    $destination = $targetDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'path' => null, 'error' => 'Gagal menyimpan file ke server.'];
    }

    return ['success' => true, 'path' => $targetUrl . $filename, 'error' => null];
}
