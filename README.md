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

## Skema Database (10 Tabel — Modul 9)

`users`, `credits`, `konfigurasi_credits`, `transaksi_pos`, `konten`, `bahan`,
`reservasi`, `workshops`, `pendaftaran_workshop`, `notifikasi`.

Detail atribut, tipe data, ENUM, dan validasi mengikuti **Modul 8 (Kamus Data)**
dan **Modul 9 (Desain Database Relasional)**.

---

## Konsistensi dengan Modul 1–12

- **Modul 3 (Arsitektur)**: PHP + MySQL, session + bcrypt, file di direktori server.
- **Modul 5 (Desain Input)**: 14 form diimplementasikan sebagai form HTML dengan
  validasi dua lapisan (HTML5 + PHP server).
- **Modul 6 (DFD/Site Map)**: struktur folder & routing mengikuti site map per peran.
- **Modul 7 (BPMN)**: alur reservasi, review konten, dan transaksi POS
  diimplementasikan sebagai transaksi MySQL atomik.
- **Modul 8/9 (Kamus Data & Database)**: seluruh nama tabel, kolom, ENUM, dan
  tipe data sama persis dengan skema PDM.
- **Modul 11 (UI/UX)**: tema dark gold/obsidian, badge tier, modal konfirmasi,
  toast notification, validasi real-time, empty state — sesuai mockup Figma.
