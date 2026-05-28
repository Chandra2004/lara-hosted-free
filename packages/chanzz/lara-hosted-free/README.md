# Lara Hosted Free 🚀

**Package Laravel untuk mempermudah deploy & operasional di Shared Hosting / Free Hosting.**

Banyak developer pemula maupun UMKM yang tidak memiliki biaya untuk menyewa VPS premium. Package ini hadir sebagai solusi agar aplikasi Laravel tetap bisa dikelola dengan nyaman di lingkungan shared hosting — tanpa akses SSH asli sekalipun.

---

## 🛠️ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| **Web SSH Terminal** | Eksekusi perintah Artisan & sistem langsung dari browser (AJAX-based, riwayat command, timeout 60 detik) |
| **Log Viewer Integration** | Integrasi `opcodesio/log-viewer` dengan keamanan terpadu (opsional) |
| **Auto Public Path** | Binding otomatis ke `public_html`, `htdocs`, atau folder custom |
| **Storage Cleaner** | Command `app:clean` dengan mode `--dry-run`, laporan ukuran file, dan proteksi folder |
| **Proteksi Keamanan** | Login berlapis: bcrypt, One-Time Token, IP lockout, honeypot, session timeout, security headers |
| **.htaccess Auto** | Redirect root ke folder public untuk shared hosting |

---

## 📋 Persyaratan Sistem

- **PHP** >= 8.0
- **Laravel** 10.x / 11.x / 12.x / 13.x
- **Cache Driver** `file` atau `database` (dibutuhkan untuk fitur IP lockout)
- **Session Driver** `file` atau `database`
- *(Opsional)* `opcodesio/log-viewer` ^3.24 — untuk fitur Log Viewer

---

## 💾 Instalasi

### Via Packagist (Produksi)

```bash
composer require chanzz/lara-hosted-free
```

### Via Path Repository (Development Lokal)

Tambahkan di `composer.json` root project Anda:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/chanzz/lara-hosted-free"
    }
],
"require": {
    "chanzz/lara-hosted-free": "1.0.0"
}
```

Lalu jalankan:

```bash
composer update
```

> Composer akan membuat symlink otomatis — perubahan pada folder `packages/` langsung aktif secara realtime.

---

## ⚙️ Konfigurasi

### 1. Publish File Konfigurasi

```bash
php artisan vendor:publish --tag=lara-hosted-config
```

Ini akan menyalin file `config/lara-hosted-free.php` ke aplikasi Anda.

### 2. Publish .htaccess (Shared Hosting)

```bash
php artisan vendor:publish --tag=lara-hosted-htaccess
```

Ini akan menyalin file `.htaccess` ke root project Anda untuk mengarahkan semua traffic ke folder `public/`.

### 3. Setup Environment Variables (`.env`)

Untuk deployment di server hosting (produksi), sangat disarankan untuk mengatur variabel environment secara eksplisit di file `.env`. 

#### 📋 Variabel Wajib untuk Keamanan Produksi
Anda **wajib** menambahkan baris berikut ke `.env` server hosting Anda demi keamanan:

```env
# ============================================================
# Lara Hosted Free — Admin Tools Settings
# ============================================================

# Aktifkan/nonaktifkan Web SSH & Log Viewer (true/false)
WEB_CONFIG=true

# Kredensial Admin Utama (PASSWORD HARUS HASH BCRYPT!)
# GANTI default username & password ini segera setelah instalasi!
USERNAME_ADMIN="admin_super_kamu"
PASSWORD_ADMIN='$2y$12$HASH_BCRYPT_MILIK_ANDA_DISINI'

# Path folder public (sesuaikan dengan hosting Anda, misal: public_html, htdocs)
PUBLIC_PATH=public

# URL Paths Kustom — WAJIB GANTI agar tidak terdeteksi bot scanner otomatis!
SSH_PATH_WEB="ops-terminal-xyz99"
LOG_PATH_WEB="log-viewer-abc77"
LOGIN_PATH_WEB="secure-gate-pqr55"

# Batasan Login & Sesi
MAX_LOGIN_ATTEMPTS=5           # Jumlah percobaan login salah sebelum IP dikunci
LOCKOUT_MINUTES=15             # Durasi penguncian IP (menit)
SESSION_TIMEOUT_MINUTES=30     # Auto-logout jika tidak ada aktivitas (menit)
```

> [!WARNING]
> **Selalu ganti password default!** Gunakan perintah berikut di terminal lokal untuk menghasilkan hash bcrypt:
> ```bash
> php artisan tinker
> >>> Hash::make('password_rahasia_kamu')
> ```
> Letakkan hasil hash di variabel `PASSWORD_ADMIN` dengan dibungkus tanda kutip tunggal `'` agar karakter khusus di dalamnya tidak corrupt.

---

### 🛡️ Panduan Stabilitas Shared Hosting (Sangat Penting)

Pada shared hosting, lingkungan server sering kali memiliki batasan resource atau rentan terhadap database crash. Ikuti rekomendasi berikut untuk menjamin panel admin Web SSH Anda 100% tangguh:

#### 1. Gunakan File-Based Driver untuk Session & Cache
Pastikan konfigurasi `.env` Anda menggunakan driver berbasis file:
```env
SESSION_DRIVER=file
CACHE_STORE=file
```
* **Mengapa?** Jika database Anda mengalami downtime (crash/overload), driver session/cache berbasis `database` akan ikut mati total. Dengan menggunakan `file`, Anda tetap bisa mengakses halaman `/secure-gate-pqr55` (login) dan `/ops-terminal-xyz99` (Web SSH) untuk menjalankan perintah perbaikan database (seperti `php artisan migrate`) saat database sedang mati!
* **Dukungan Resiliensi Package:** Lara Hosted Free telah dilengkapi dengan *safe fallback automatic wrappers*. Jika database Anda mati dan cache global Anda menggunakan driver database, sistem akan secara otomatis mengalihkan penyimpanan data lockout login sementara ke native PHP `session()`, mencegah terjadinya error `500 Database Connection Refused` pada halaman Web SSH!

#### 2. Kredensial Environment Bersih
Pastikan tidak ada spasi di luar tanda kutip pada nilai `.env`. Jika terdapat karakter khusus pada password atau path, bungkus menggunakan tanda kutip ganda atau tunggal:
`PASSWORD_ADMIN='$2y$12$...'`
`APP_NAME="Web Saya"`


---

## 🔐 Sistem Keamanan

Package ini dilengkapi dengan beberapa lapisan keamanan:

### Autentikasi

| Fitur | Deskripsi |
|-------|-----------|
| **Bcrypt Hash** | Password disimpan sebagai bcrypt hash, bukan plain text |
| **One-Time Token** | Token sekali pakai (64 karakter) untuk mencegah replay attack |
| **Session Regeneration** | Session di-regenerate setelah login untuk mencegah session fixation |
| **Timing-safe Comparison** | Menggunakan `hash_equals()` untuk mencegah timing attack |

### Proteksi Brute-force

| Fitur | Deskripsi |
|-------|-----------|
| **IP Lockout** | IP dikunci setelah `MAX_LOGIN_ATTEMPTS` kali gagal login |
| **Throttle Middleware** | Rate limiting pada endpoint login |
| **Honeypot Fields** | Field tersembunyi untuk mendeteksi bot otomatis |
| **Login Logging** | Semua percobaan login dicatat di Laravel Log (IP + User-Agent) |

### Proteksi HTTP

| Header | Nilai |
|--------|-------|
| `X-Frame-Options` | `DENY` — cegah clickjacking |
| `X-Content-Type-Options` | `nosniff` — cegah MIME sniffing |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `no-referrer` |
| `Cache-Control` | `no-store, no-cache` — cegah caching data sensitif |

### Proteksi Terminal

Perintah-perintah berbahaya diblokir secara otomatis:

| Kategori | Contoh yang Diblokir |
|----------|---------------------|
| Destruktif | `rm -rf`, `mkfs`, `dd if=` |
| Sistem | `shutdown`, `reboot`, `sudo` |
| User Management | `passwd`, `useradd`, `userdel` |
| Reverse Shell | `nc -l`, `curl \| bash`, `wget \| sh` |
| Interaktif | `tinker`, `serve`, `nano`, `vim` |
| Arbitrary Code | `php -r` |

### Session & Timeout

- **Auto-logout** setelah periode tidak aktif (`SESSION_TIMEOUT_MINUTES`)
- **CSRF Protection** pada semua form
- **Double-submit Prevention** pada tombol login

---

## 📑 Integrasi Log Viewer (Opsional)

Jika Anda ingin menggunakan fitur Log Viewer, install package `opcodesio/log-viewer` terlebih dahulu:

```bash
composer require opcodesio/log-viewer
```

Kemudian publish konfigurasi log-viewer dan sesuaikan beberapa opsi pada file `config/log-viewer.php`:

```bash
php artisan vendor:publish --provider="Opcodes\LogViewer\LogViewerServiceProvider"
```

Ubah konfigurasi berikut:

```php
// config/log-viewer.php

// Sinkronkan toggle on/off dengan WEB_CONFIG
'enabled' => env('WEB_CONFIG', true),

// Sesuaikan path URL dengan admin-log
'route_path' => env('LOG_PATH_WEB', 'admin-log'),

// Middleware — tambahkan AdminAuth dari package
'middleware' => [
    'web',
    \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
    \Chanzz\LaraHostedFree\Http\Middleware\AdminAuth::class,
],

// API Middleware — WAJIB pakai 'web' agar session berfungsi
'api_middleware' => [
    'web',
    \Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
    \Chanzz\LaraHostedFree\Http\Middleware\AdminAuth::class,
],
```

> **Catatan**: Package ini otomatis melakukan bypass Gate `viewLogViewer` bawaan opcodesio agar keamanan sepenuhnya ditangani oleh middleware `AdminAuth`.

---

## 🌐 Cara Penggunaan

### 1. Akses Web SSH Terminal

Buka browser Anda dan kunjungi:

```
https://domain-anda.com/admin-ssh
```
*(atau sesuaikan dengan nilai `SSH_PATH_WEB` di `.env`)*

#### Perintah yang Dapat Dijalankan

| Tipe | Contoh |
|------|--------|
| **Artisan** | `php artisan list`, `artisan migrate`, `artisan route:list`, `artisan optimize` |
| **Sistem** | `ls -la`, `dir`, `composer -v`, `git status`, `df -h`, `php -v` |
| **Package** | `app:clean`, `app:clean --dry-run`, `app:clean --force` |

#### Fitur Keyboard Terminal

| Shortcut | Fungsi |
|----------|--------|
| `Enter` | Jalankan perintah |
| `↑` / `↓` | Navigasi riwayat perintah (command history) |
| `clear` | Bersihkan layar terminal |
| `help` | Tampilkan daftar panduan perintah |

#### Catatan Teknis Terminal

- **Timeout**: Perintah yang berjalan lebih dari **60 detik** akan otomatis dibatalkan
- **Loading indicator**: Spinner muncul selama perintah diproses — jangan ketik perintah baru saat proses berjalan
- **Output bersih**: ANSI color code di-strip otomatis agar output mudah dibaca
- **Stderr digabung**: Error output (`stderr`) ditampilkan bersama output normal
- **CSRF expired**: Jika session expired dan muncul pesan kuning, tunggu halaman reload otomatis
- **Artisan otomatis**: Semua perintah `migrate*` otomatis mendapat flag `--force` dan `--no-ansi`

### 2. Akses Log Viewer

```
https://domain-anda.com/admin-log
```
*(atau sesuaikan dengan nilai `LOG_PATH_WEB` di `.env`)*

### 3. Autentikasi

Gunakan kredensial yang sudah Anda definisikan di `.env`:
- **Username**: nilai dari `USERNAME_ADMIN`
- **Password**: password asli yang Anda gunakan saat membuat hash bcrypt

Jika session berakhir (timeout atau logout manual), Anda akan diminta login kembali.

---

## 🧹 Storage Cleaner (`app:clean`)

Command untuk membersihkan file yang tidak diperlukan dan menghemat ruang penyimpanan hosting.

### Penggunaan

```bash
# Preview apa yang akan dihapus (TANPA menghapus)
php artisan app:clean --dry-run

# Jalankan pembersihan (dengan konfirmasi untuk public storage)
php artisan app:clean

# Jalankan pembersihan tanpa konfirmasi (untuk CI/CD atau cron)
php artisan app:clean --force
```

### Apa yang Dibersihkan?

| # | Kategori | Deskripsi |
|---|----------|-----------|
| 1 | Cache Framework | View cache, route cache, config cache, event cache |
| 2 | File Log | Semua file `*.log` di `storage/logs/` |
| 3 | Temporary Files | File & folder sampah di `storage/app/` (kecuali yang diproteksi) |
| 4 | Livewire Trash | File temporary upload Livewire |
| 5 | Public Storage | File di `storage/app/public/` (opsional, butuh konfirmasi) |
| 6 | Sessions | Semua file session di `storage/framework/sessions/` |

### Proteksi Folder

Folder-folder berikut **tidak akan dihapus** (konfigurasi di `clean_exclude`):

```php
'clean_exclude' => [
    'uploads',       // File upload user
    'livewire-tmp',  // Livewire temporary
    'private',       // File privat
    'public',        // Public storage (dilindungi secara terpisah)
],
```

### Output

Command ini menampilkan:
- Detail setiap file/folder yang dihapus beserta ukurannya
- Folder yang diproteksi ditandai dengan ikon 🔒
- Tabel ringkasan di akhir berisi jumlah file dan total ukuran yang dihemat

---

## 📁 Struktur Folder Hosting

### Sebelum (Default Laravel)

```
/home/user/
├── laravel-project/        ← Root Laravel
│   ├── app/
│   ├── config/
│   ├── public/             ← Folder public default
│   └── ...
```

### Sesudah (Shared Hosting dengan lara-hosted-free)

```
/home/user/
├── laravel-project/        ← Root Laravel (di atas public_html)
│   ├── app/
│   ├── config/
│   ├── .htaccess           ← dari vendor:publish --tag=lara-hosted-htaccess
│   └── ...
└── public_html/            ← Folder public hosting
    ├── index.php
    ├── .htaccess
    └── ...
```

Set `PUBLIC_PATH=../public_html` di `.env` agar Laravel mengenali folder public yang benar.

---

## 🔧 Referensi Variabel Environment

| Variabel | Default | Deskripsi |
|----------|---------|-----------|
| `WEB_CONFIG` | `true` | Aktifkan/nonaktifkan Web SSH & Log Viewer |
| `USERNAME_ADMIN` | `admin` | Username login admin |
| `PASSWORD_ADMIN` | *(bcrypt hash)* | Password admin (bcrypt hash) |
| `PUBLIC_PATH` | `public` | Path folder public (`public_html`, `htdocs`, dll) |
| `SSH_PATH_WEB` | `admin-ssh` | URL path untuk Web SSH Terminal |
| `LOG_PATH_WEB` | `admin-log` | URL path untuk Log Viewer |
| `LOGIN_PATH_WEB` | `admin-login` | URL path untuk endpoint login |
| `MAX_LOGIN_ATTEMPTS` | `5` | Jumlah percobaan login sebelum IP dikunci |
| `LOCKOUT_MINUTES` | `15` | Durasi lockout dalam menit |
| `SESSION_TIMEOUT_MINUTES` | `30` | Auto-logout jika idle (menit) |

---

## ⚠️ Tips Keamanan Produksi

1. **Ganti semua URL path default** — Bot scanner mencari path umum seperti `admin-ssh`. Ganti ke sesuatu yang unik:
   ```env
   SSH_PATH_WEB="my-ops-panel-x7k9"
   LOG_PATH_WEB="my-logs-viewer-p3m2"
   LOGIN_PATH_WEB="my-secure-gate-z4n8"
   ```

2. **Ganti password default segera** — Password default `secret123` **WAJIB** diganti sebelum deploy.

3. **Nonaktifkan saat tidak digunakan** — Set `WEB_CONFIG=false` di `.env` jika Anda tidak membutuhkan Web SSH & Log Viewer untuk sementara waktu.

4. **Monitor log** — Periksa `storage/logs/laravel.log` secara berkala. Semua percobaan login (berhasil/gagal) dicatat dengan prefix `[LaraHostedFree]`.

5. **Pastikan `APP_URL` benar** — Fitur Open Redirect Protection membutuhkan `APP_URL` yang akurat di `.env`.

6. **Gunakan HTTPS** — Pastikan hosting Anda mendukung SSL. Data login dikirim via POST dan akan terekspos jika tidak menggunakan HTTPS.

---

## 🐛 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| **Login selalu gagal** | Pastikan `PASSWORD_ADMIN` berisi hash bcrypt (bukan plain text). Generate ulang: `php artisan tinker` → `Hash::make('password')`. Cek tidak ada spasi/kutip ganda di `.env`. |
| **Terminal tidak menampilkan output** | Pastikan `proc_open` tidak ada di `disable_functions` di `php.ini` hosting. Coba perintah `php artisan list` — jika artisan bekerja tapi sistem tidak, kemungkinan `proc_open` diblokir. |
| **Output artisan muncul tapi aneh** | ANSI strip sudah otomatis. Jika masih ada karakter aneh, pastikan package versi terbaru digunakan. |
| **Perintah tidak ada respons / loading terus** | Perintah mungkin timeout (>60 detik). Coba perintah lebih sederhana dulu untuk verifikasi koneksi. |
| **Muncul pesan kuning "CSRF token expired"** | Session habis. Halaman akan reload otomatis dalam 2 detik — login kembali. |
| **Session cepat hilang** | Naikkan `SESSION_TIMEOUT_MINUTES` dan pastikan `SESSION_DRIVER=file`. |
| **Log Viewer tidak muncul** | Pastikan `opcodesio/log-viewer` sudah terinstall dan middleware sudah dikonfigurasi di `config/log-viewer.php`. |
| **429 Too Many Requests** | IP dikunci karena terlalu banyak percobaan login. Tunggu sesuai nilai `LOCKOUT_MINUTES`. |
| **Public path tidak benar** | Cek nilai `PUBLIC_PATH` di `.env`. Untuk shared hosting biasanya `../public_html` atau `../htdocs`. |
| **`.htaccess` tidak bekerja** | Pastikan `mod_rewrite` diaktifkan di Apache hosting Anda. Hubungi penyedia hosting jika perlu. |
| **`app:clean` tidak merespons** | Coba jalankan dengan `--dry-run` dulu untuk verifikasi. Pastikan folder `storage/` memiliki permission yang benar (`755`). |

---

## 📦 Artisan Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan app:clean` | Bersihkan storage (cache, log, session, temp) |
| `php artisan app:clean --dry-run` | Preview apa yang akan dihapus tanpa benar-benar menghapus |
| `php artisan app:clean --force` | Bersihkan tanpa konfirmasi interaktif |
| `php artisan vendor:publish --tag=lara-hosted-config` | Publish file konfigurasi ke `config/lara-hosted-free.php` |
| `php artisan vendor:publish --tag=lara-hosted-htaccess` | Publish file `.htaccess` ke root project |

---

## 📄 Lisensi

MIT License — Bebas digunakan untuk proyek pribadi maupun komersial.

---

*Dibuat dengan 💖 oleh [Chandra Tri Antomo](mailto:chandratriantomo123@gmail.com).*
