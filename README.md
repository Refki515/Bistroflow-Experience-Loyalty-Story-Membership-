# BistroFlow ERP — Experience Loyalty & Story Membership
## "Kuliner dengan Cerita"

Implementasi PHP + MySQL untuk modul **Experience Loyalty & Story Membership**,
konsisten dengan Laporan Praktikum Software Engineering Modul 1–12
(Muhammad Refki Andesta, NIM 240605110232, UIN Maulana Malik Ibrahim Malang)
dan mockup desain Figma (tema dark "obsidian/gold").

---

## Teknologi
- **Backend**: PHP 8 (native, tanpa framework) + PDO MySQL (prepared statements)
- **Database**: MySQL / MariaDB
- **Autentikasi**: PHP Session + bcrypt (`password_hash` / `password_verify`)
- **File upload**: `move_uploaded_file()` ke direktori server (`/uploads/...`), path disimpan di MySQL
- **Frontend**: HTML + CSS murni (custom, tema gold/obsidian sesuai mockup), vanilla JavaScript
- **Integrasi POS**: REST API (`/api/transaksi_pos.php`) menerima JSON via HTTP POST

Tidak ada dependensi eksternal berbayar (tidak ada Node.js, Firebase, JWT, Cloudinary, atau cloud storage).

---

## Instalasi

1. **Buat database** dan import skema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE bistroflow_erp"
   mysql -u root -p bistroflow_erp < database/schema.sql
   ```

2. **Konfigurasi koneksi** di `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'bistroflow_erp');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **Pastikan folder upload writable**:
   ```bash
   chmod -R 755 uploads/
   ```

4. **Jalankan dengan PHP built-in server** (untuk development):
   ```bash
   php -S localhost:8000
   ```
   Atau deploy ke Apache/Nginx dengan document root di folder ini.

---

## Akun Demo (password: `password123`)

| Role     | Email                     | Tier   |
|----------|---------------------------|--------|
| Admin    | admin@bistroflow.com      | -      |
| Chef     | chef@bistroflow.com       | -      |
| Produsen | produsen@bistroflow.com   | -      |
| Member   | member@bistroflow.com     | Gold   |

---

## Struktur Folder

```
bistroflow/
├── config/
│   ├── database.php       # Koneksi PDO MySQL
│   └── auth.php            # Session, role guard, helper
├── includes/
│   ├── header.php          # <head>, font, CSS
│   ├── sidebar.php          # Navigasi 5 menu (role-based)
│   ├── footer.php           # Penutup layout
│   └── functions.php        # Bootstrap + helper bisnis (tier, credits, notifikasi, upload)
├── assets/
│   ├── css/style.css       # Tema gold/obsidian (sesuai mockup Figma)
│   └── js/app.js            # Modal, toast, validasi, countdown
├── auth/
│   ├── login.php           # Login & Register (toggle)
│   └── logout.php
├── member/                  # Member Portal
│   ├── dashboard.php
│   ├── loyalty.php          # Loyalty & Credits + modal tukar credits
│   ├── storytelling.php     # Galeri Culinary Storytelling
│   ├── konten_detail.php
│   ├── workshop.php         # Daftar & riwayat workshop
│   ├── reservasi.php        # Seasonal Reserves (tier-based access)
│   ├── notifikasi.php
│   └── profil.php
├── chef/                     # Chef's Table
│   ├── dashboard.php        # Chef's Overview
│   ├── upload_konten.php    # Upload Behind-the-Scenes
│   ├── input_menu.php       # Input menu + cerita + bahan terkait
│   └── peserta_workshop.php # View peserta (read-only)
├── produsen/                 # Producer Hub
│   ├── dashboard.php
│   ├── upload_bahan.php     # Cerita asal bahan
│   └── update_stok.php
├── admin/                     # Admin Control + System Settings
│   ├── dashboard.php        # Command Center
│   ├── kelola_member.php
│   ├── review_konten.php    # Storytelling Queue
│   ├── kelola_workshop.php  # Buat jadwal + konfirmasi kehadiran
│   ├── kelola_bahan.php
│   ├── konfigurasi.php      # System Settings - aturan credits
│   ├── konfirmasi_reservasi.php
│   └── laporan_pos.php
├── api/
│   └── transaksi_pos.php    # REST API integrasi POS
├── uploads/                   # File fisik (konten, sertifikat, rekaman, bahan)
├── database/
│   └── schema.sql            # Skema MySQL (10 tabel, sesuai Modul 9 PDM)
└── index.php                  # Landing Page "Kuliner dengan Cerita"
```

---
