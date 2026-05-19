# 📦 Panduan Upload NOXARA ke Shared Hosting IDCloudHost

## ✅ Syarat Hosting
- PHP **7.4 – 8.2** (disarankan 8.1 atau 8.2)
- MySQL / MariaDB
- mod_rewrite aktif (biasanya sudah aktif di IDCloudHost)
- Minimal RAM: 512MB
- Storage: minimal 500MB

---

## 🗂️ LANGKAH 1 — Siapkan File

### Struktur folder yang harus diupload ke `public_html/`:
```
public_html/
├── index.php
├── .htaccess
├── config/
│   ├── bootstrap.php
│   ├── config.php          ← EDIT INI DULU!
│   ├── constants.php
│   ├── csrf.php
│   ├── database.php        ← EDIT INI DULU!
│   └── session.php
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
├── assets/
│   ├── css/
│   └── img/
├── includes/
├── pages/
├── admin/
├── uploads/
└── logs/
```

> ⚠️ **JANGAN upload folder**: `.git/`, `database/*.sql` (upload via phpMyAdmin)

---

## ⚙️ LANGKAH 2 — Edit Konfigurasi (WAJIB)

### 📄 Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');         // Biasanya 'localhost'
define('DB_NAME', 'nama_database');     // Nama DB di cPanel
define('DB_USER', 'user_database');     // User DB di cPanel
define('DB_PASS', 'password_database'); // Password DB
```

### 📄 Edit `config/config.php`:
```php
define('BASE_URL', 'https://namadomain.com'); // Ganti dengan domain kamu
// Jika website di subfolder, contoh: https://namadomain.com/noxara
// define('BASE_URL', 'https://namadomain.com/noxara');

// Aktifkan HTTPS redirect di .htaccess (uncomment 2 baris):
// RewriteCond %{HTTPS} off
// RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 📄 Edit `config/session.php` (jika pakai HTTPS):
```php
ini_set('session.cookie_secure', '1');  // Ubah '0' → '1' jika pakai HTTPS/SSL
```

---

## 🗄️ LANGKAH 3 — Import Database

1. Buka **cPanel** → **phpMyAdmin**
2. Buat database baru (misal: `noxara_db`)
3. Buat user database dan **assign ke database** dengan semua privilege
4. Klik database → tab **Import**
5. Upload file `database/dashboard.sql`
6. Klik **Go**

---

## 📤 LANGKAH 4 — Upload File via cPanel

### Cara A: File Manager cPanel
1. Login cPanel IDCloudHost
2. Buka **File Manager** → masuk ke `public_html/`
3. Klik **Upload** → upload semua file
4. Atau: compress semua file jadi `.zip`, upload, lalu **Extract** di File Manager

### Cara B: FTP (disarankan untuk file banyak)
1. Di cPanel → **FTP Accounts** → buat akun FTP
2. Gunakan FileZilla:
   - Host: ftp.namadomain.com
   - User: ftp_user@namadomain.com
   - Password: (password FTP)
   - Port: 21
3. Upload semua file ke `/public_html/`

---

## 🔒 LANGKAH 5 — Set Permission File/Folder

Di File Manager cPanel, set permission:

| Path | Permission |
|------|-----------|
| `uploads/` dan semua subfolder | **755** |
| `logs/` | **755** |
| `config/database.php` | **644** |
| `config/config.php` | **644** |
| `.htaccess` | **644** |
| Semua file `.php` | **644** |

---

## 🔧 LANGKAH 6 — Verifikasi PHP Version

1. Di cPanel → **Select PHP Version** (MultiPHP Manager)
2. Pilih **PHP 8.1** atau **8.2**
3. Aktifkan ekstensi: `mysqli`, `mbstring`, `json`, `curl`, `openssl`, `fileinfo`

---

## 🌐 LANGKAH 7 — Test Website

Buka browser:
- `https://namadomain.com/` → Landing page
- `https://namadomain.com/auth/login.php` → Login
- `https://namadomain.com/auth/register.php` → Register

---

## ⚠️ Troubleshooting Umum

### Error: "500 Internal Server Error"
- Cek file `.htaccess` — hapus baris yang tidak didukung
- Di `.htaccess`, ubah:
  ```
  <IfModule mod_php.c>  →  <IfModule mod_php8.c>
  ```
  atau hapus seluruh blok `mod_php` jika error

### Error: "Database connection failed"
- Pastikan `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` benar
- Di IDCloudHost shared hosting, host biasanya `localhost`

### Error: "RewriteEngine not found" / halaman 404
- Cek di cPanel → **Apache Handlers** atau hubungi CS IDCloudHost
- Atau tambahkan di awal `.htaccess`: `Options +FollowSymLinks`

### Error: "Permission denied" di uploads/
- Set permission folder `uploads/` ke **755** atau **777** (sementara)

### Halaman login/register tidak mau redirect
- Pastikan `BASE_URL` di `config.php` sudah benar (tanpa trailing slash)
- Pastikan tidak ada spasi di awal file PHP

### Session tidak berfungsi
- Cek PHP session save path: tambahkan di `session.php`:
  ```php
  ini_set('session.save_path', sys_get_temp_dir());
  ```

---

## 📋 Checklist Upload

- [ ] Edit `config/database.php` dengan kredensial DB
- [ ] Edit `config/config.php` ubah BASE_URL
- [ ] Import `database/dashboard.sql` via phpMyAdmin
- [ ] Upload semua file ke `public_html/`
- [ ] Set permission folder `uploads/` dan `logs/` ke 755
- [ ] Set PHP version ke 8.1 atau 8.2 di cPanel
- [ ] Test buka website di browser
- [ ] Aktifkan SSL (Let's Encrypt gratis di cPanel)
- [ ] Aktifkan HTTPS redirect di `.htaccess`

---

## 💡 Tips IDCloudHost

- **SSL Gratis**: cPanel → **Let's Encrypt SSL** → Install untuk domain kamu
- **Backup**: cPanel → **Backup Wizard** → backup berkala
- **Email**: jika ingin kirim email reset password, aktifkan **SMTP** di cPanel → Email Accounts
- **PHP Mail**: tambahkan di `config.php` untuk email:
  ```php
  ini_set('SMTP', 'mail.namadomain.com');
  ini_set('sendmail_from', 'noreply@namadomain.com');
  ```

---

*Dibuat untuk NOXARA Platform v1.0 — IDCloudHost Shared Hosting*
