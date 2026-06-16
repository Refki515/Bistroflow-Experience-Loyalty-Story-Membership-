<?php
/**
 * BistroFlow ERP - REST API: Integrasi Transaksi POS
 * Endpoint: POST /api/transaksi_pos.php
 *
 * Sesuai Modul 8 (Kamus Data) & Modul 9 (PDM tabel transaksi_pos):
 * - Menerima payload JSON dari sistem kasir restoran (HTTP POST)
 * - Validasi payload, hitung credits berdasarkan konfigurasi_credits
 * - Update saldo credits Member secara atomik (MySQL transaction)
 * - Evaluasi tier (Gold/Silver/Bronze)
 * - Mengembalikan response JSON ke sistem POS
 *
 * Payload contoh:
 * {
 * "user_id_member": 4,
 * "nominal_transaksi": 150000.00,
 * "tanggal_transaksi_pos": "2026-06-16 19:30:00"
 * }
 */

require_once __DIR__ . '/../includes/functions.php';

// Atur header response berupa JSON
header('Content-Type: application/json');

// 1. VALIDASI METHOD REQUEST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'gagal', 'message' => 'Method tidak diizinkan. Gunakan POST.']);
    exit;
}

// 2. READ & DECODE PAYLOAD JSON
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'gagal', 'message' => 'Payload JSON tidak valid.']);
    exit;
}

// 3. INISIALISASI VARIABEL PAYLOAD
$userId = (int)($payload['user_id_member'] ?? 0);
$nominal = $payload['nominal_transaksi'] ?? null;
$tanggalTransaksi = $payload['tanggal_transaksi_pos'] ?? date('Y-m-d H:i:s');

$errors = [];

// 4. VALIDASI ATURAN DATA (Modul 8)
if ($userId <= 0) {
    $errors[] = 'user_id_member wajib ada dan harus merujuk ke user_id yang valid.';
}
if (!is_numeric($nominal) || (float)$nominal <= 0) {
    $errors[] = 'nominal_transaksi harus berupa angka positif lebih dari 0.';
}
if (strtotime($tanggalTransaksi) > time()) {
    $errors[] = 'tanggal_transaksi_pos tidak boleh tanggal di masa depan.';
}

$pdo = getDB();

// 5. VALIDASI EKSISTENSI & ROLE USER DI DATABASE
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT user_id, role, tier_member FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        $errors[] = 'Member tidak ditemukan dalam sistem.';
    } elseif ($member['role'] !== 'member') {
        $errors[] = 'Member tidak ditemukan dalam sistem.';
    }
}

// 6. PENANGANAN JIKA VALIDASI GAGAL
if (!empty($errors)) {
    http_response_code(422);

    // Catat sebagai transaksi gagal di tabel transaksi_pos untuk kebutuhan audit (jika ID valid)
    if ($userId > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO transaksi_pos (user_id_member, nominal_transaksi, credits_diberikan, status_sinkronisasi, tanggal_transaksi_pos)
             VALUES (?, ?, 0, 'gagal', ?)"
        );
        $stmt->execute([$userId, is_numeric($nominal) ? $nominal : 0, $tanggalTransaksi]);
    }

    echo json_encode(['status' => 'gagal', 'message' => implode(' ', $errors)]);
    exit;
}

// ============================================================
// 7. PROSES HITUNG CREDITS & UPDATE SALDO SECARA ATOMIK (TRANSACTION)
// ============================================================
try {
    $pdo->beginTransaction();

    // Ambil aturan konversi point dari system configuration
    $config = getKonfigurasiCredits();
    $creditsDiberikan = (int)floor((float)$nominal / (int)$config['aturan_credits_per_nominal']);

    // Simpan data ke tabel transaksi_pos dengan status 'berhasil'
    $stmt = $pdo->prepare(
        "INSERT INTO transaksi_pos (user_id_member, nominal_transaksi, credits_diberikan, status_sinkronisasi, tanggal_transaksi_pos)
         VALUES (?, ?, ?, 'berhasil', ?)"
    );
    $stmt->execute([$userId, $nominal, $creditsDiberikan, $tanggalTransaksi]);
    $posId = (int)$pdo->lastInsertId();

    // AMAN: Ambil saldo terakhir dan pastikan tidak berstatus null/false (di-force ke angka 0 jika kosong)
    $saldoSekarang = getSaldoCredits($userId);
    $saldoSekarang = $saldoSekarang ? (int)$saldoSekarang : 0; 
    
    // Akumulasikan saldo lama dengan bonus credit yang baru didapat
    $saldoBaru = $saldoSekarang + $creditsDiberikan;

    // Catat baris riwayat penambahan credit baru ke tabel credits
    $stmt = $pdo->prepare(
        "INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit)
         VALUES (?, ?, ?, 'masuk', ?)"
    );
    $stmt->execute([$userId, $creditsDiberikan, $saldoBaru, "Transaksi POS #$posId"]);

    // Commit transaksi database jika seluruh operasi di atas sukses tanpa hambatan
    $pdo->commit();

    // Sinkronisasi level/tier member berdasarkan akumulasi saldo credit yang baru
    syncTierMember($userId, $saldoBaru);

    // Kirim response sukses ke sistem POS Kasir
    echo json_encode([
        'status' => 'berhasil',
        'pos_transaksi_id' => $posId,
        'credits_diberikan' => $creditsDiberikan,
        'saldo_credits_baru' => $saldoBaru,
        'tier_member' => getTierProgress($saldoBaru)['current'],
    ]);

} catch (Exception $ex) {
    // Batalkan semua perubahan jika di tengah jalan terjadi error/gagal sistem
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'gagal', 
        'message' => 'Terjadi kesalahan internal: ' . $ex->getMessage()
    ]);
}