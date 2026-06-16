<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirectByRole(currentRole());
}

$errors = [];
$mode = $_GET['mode'] ?? 'login'; // 'login' atau 'register'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Email dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Email atau password salah.';
            } elseif ($user['status_akun'] !== 'aktif') {
                $errors[] = 'Akun Anda tidak aktif. Silakan hubungi Admin.';
            } else {
                createUserSession($user);
                redirectByRole($user['role']);
            }
        }
        $mode = 'login';
    }

    if ($action === 'register') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['nomor_telepon'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validasi server (Modul 5: dua lapisan validasi)
        if ($nama === '' || strlen($nama) < 3) {
            $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';
        }
        if (!preg_match('/^08[0-9]{8,11}$/', $telepon)) {
            $errors[] = 'Nomor telepon tidak valid. Diawali 08, 10-13 digit.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }

        if (empty($errors)) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email tidak valid atau sudah terdaftar.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (nama_lengkap, email, password, nomor_telepon, role, tier_member, status_akun)
                     VALUES (?, ?, ?, ?, 'member', 'bronze', 'aktif')"
                );
                $stmt->execute([$nama, $email, $hash, $telepon]);
                $newId = (int)$pdo->lastInsertId();

                // Saldo credits awal 0
                $stmt = $pdo->prepare(
                    "INSERT INTO credits (user_id, jumlah_credits, saldo_credits, jenis_transaksi_credit, keterangan_credit)
                     VALUES (?, 0, 0, 'masuk', 'Akun baru terdaftar')"
                );
                $stmt->execute([$newId]);

                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$newId]);
                $user = $stmt->fetch();
                createUserSession($user);
                redirectByRole('member');
            }
        }
        $mode = 'register';
    }
}

$pageTitle = 'Autentikasi';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    body.auth-body { display: flex; min-height: 100vh; background-color: var(--color-background); }
    
    /* MODIFIKASI: Menambahkan gambar background di sisi kiri login screen */
    .auth-left {
        width: 50%;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        
        /* Silakan ganti path/url gambar di bawah ini dengan gambar dapur/bistro Anda */
        
        background:
         linear-gradient(to bottom, rgba(20, 17, 10, 0.4), rgba(20, 17, 10, 1)), 
          url('../assets/image/login.png') no-repeat center center;
        background-size: cover;
        
        border-right: 1px solid rgba(77,70,53,0.4);
        padding: var(--space-lg);
        overflow: hidden;
    }
    
    /* Overlay gradasi agar teks di atas gambar tetap terbaca dengan jelas */
    .auth-left::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(to top, rgba(20, 17, 10, 0.9), rgba(22, 19, 11, 0.4), transparent);
        z-index: 1;
    }
    
    .auth-left-content { position: relative; z-index: 2; }
    
    /* Memberikan efek bayangan pada teks agar lebih stand-out */
    .auth-left-content h1, .auth-left-content p {
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
    }

    .auth-right {
        width: 50%;
        display: flex; align-items: center; justify-content: center;
        padding: var(--space-lg);
    }
    .auth-card {
        width: 100%; max-width: 440px;
        background-color: rgba(26,26,26,0.8);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: var(--radius-2xl);
        padding: var(--space-lg);
        backdrop-filter: blur(20px);
    }
    .auth-card h2 { font-size: 28px; font-weight: 600; margin-bottom: 4px; text-align: center; }
    .auth-card .subtitle { color: var(--color-on-surface-variant); text-align: center; margin-bottom: var(--space-md); font-size: 14px; }
    .auth-switch { text-align: center; margin-top: var(--space-md); font-size: 14px; color: var(--color-on-surface-variant); }
    .auth-switch a { color: var(--color-primary); font-weight: 700; }
    .input-icon-wrap { position: relative; }
    .input-icon-wrap .material-symbols-outlined {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        color: var(--color-outline-variant); font-size: 20px;
    }
    .input-icon-wrap .form-control { padding-left: 44px; }
    @media (max-width: 900px) {
        .auth-left { display: none; }
        .auth-right { width: 100%; }
    }
</style>
<body class="auth-body">
<div class="auth-left">
    <a href="../index.php" class="auth-left-content flex items-center gap-sm" style="text-decoration: none; color: inherit; cursor: pointer;">
    <span class="material-symbols-outlined" style="color: var(--color-primary); font-size: 32px;">restaurant</span>
    <span style="font-size:20px; font-weight:700; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">BistroFlow ERP</span>
</a>
    <div class="auth-left-content" style="max-width: 480px;">
        <p class="font-mono" style="color: var(--color-primary); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 16px; font-size: 12px;">Culinary Excellence</p>
        <h1 style="font-size: 48px; line-height: 1.2; margin-bottom: 24px;">Elevate your dining experience.</h1>
        <p style="color: #e1dacb; font-size: 16px;">Seamlessly manage reservations, inventory, and staff operations with precision. Transform raw data into a curated culinary narrative.</p>
    </div>
</div>

<div class="auth-right">
    <div class="auth-card">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
            <h2>Welcome Back</h2>
            <p class="subtitle">Masuk ke sistem — tersedia untuk Member, Admin, Chef, dan Produsen</p>
            <form method="POST" action="<?= url('auth/login.php') ?>" id="loginForm">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <span class="material-symbols-outlined">mail</span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="email@contoh.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <span class="material-symbols-outlined">lock</span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full" id="loginBtn">
                    <span class="btn-label">Masuk</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                </button>
                <p class="form-help text-center" style="margin-top:8px;">Session PHP dibuat otomatis setelah login berhasil</p>
            </form>
            <div class="auth-switch">
                Belum punya akun? <a href="?mode=register">Daftar sekarang</a>
            </div>
        <?php else: ?>
            <h2>Join BistroFlow</h2>
            <p class="subtitle">Buat akun Member baru untuk mengakses sistem loyalty BistroFlow</p>
            <form method="POST" action="<?= url('auth/login.php?mode=register') ?>" id="registerForm">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="reg-nama">Nama Lengkap <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <span class="material-symbols-outlined">person</span>
                        <input type="text" class="form-control" id="reg-nama" name="nama_lengkap" placeholder="Nama lengkap Anda" required minlength="3" value="<?= e($_POST['nama_lengkap'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="reg-email">Email <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <span class="material-symbols-outlined">mail</span>
                            <input type="email" class="form-control" id="reg-email" name="email" placeholder="email@contoh.com" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <p class="form-help">Format: user@email.com</p>
                    </div>
                    <div class="form-group">
                        <label for="reg-telepon">Nomor Telepon <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <span class="material-symbols-outlined">phone</span>
                            <input type="tel" class="form-control" id="reg-telepon" name="nomor_telepon" placeholder="08xxxxxxxxxx" required pattern="^08[0-9]{8,11}$" value="<?= e($_POST['nomor_telepon'] ?? '') ?>">
                        </div>
                        <p class="form-help">Diawali 08, angka saja</p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="reg-password">Password <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <span class="material-symbols-outlined">lock</span>
                            <input type="password" class="form-control" id="reg-password" name="password" placeholder="Min. 8 karakter" required minlength="8">
                        </div>
                        <p class="form-help">Minimal 8 karakter</p>
                    </div>
                    <div class="form-group">
                        <label for="reg-confirm">Konfirmasi Password <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <span class="material-symbols-outlined">lock_clock</span>
                            <input type="password" class="form-control" id="reg-confirm" name="confirm_password" placeholder="Ulangi password" required minlength="8">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full" id="registerBtn">
                    <span class="btn-label">Daftar Sekarang</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                </button>
            </form>
            <div class="auth-switch">
                Sudah punya akun? <a href="?mode=login">Masuk di sini</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>