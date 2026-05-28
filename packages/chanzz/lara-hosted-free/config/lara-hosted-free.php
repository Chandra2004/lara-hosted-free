<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Artisan Console Control
    |--------------------------------------------------------------------------
    | Aktifkan atau nonaktifkan akses ke terminal CLI artisan berbasis web.
    |
    */
    'artisan_web' => env('WEB_CONFIG', true),

    /*
    |--------------------------------------------------------------------------
    | Kredensial Admin
    |--------------------------------------------------------------------------
    | Username dan password (bcrypt hash) untuk akses Terminal & Log Viewer.
    | Gunakan perintah: php artisan tinker -> Hash::make('password_anda')
    | untuk menghasilkan hash baru.
    |
    */
    'username' => env('USERNAME_ADMIN', 'admin'),
    'password' => env('PASSWORD_ADMIN', '$2y$12$fx61lAuR2mpQZMtJ84WA3OnG/zgBVEIJho6C7Fcc8AKr8NnFz68pa'), // Default password: secret123

    /*
    |--------------------------------------------------------------------------
    | Folder Public Kustom
    |--------------------------------------------------------------------------
    | Nama atau path direktori public. Sangat berguna untuk shared hosting
    | yang menggunakan 'public_html' or 'htdocs'.
    |
    */
    'public_path' => env('PUBLIC_PATH', 'public'),

    /*
    |--------------------------------------------------------------------------
    | URL Paths (Routing)
    |--------------------------------------------------------------------------
    | Path URL kustom untuk mengakses terminal Web SSH, Log Viewer, dan Login.
    | TIPS KEAMANAN: Ganti path default agar tidak mudah ditemukan oleh scanner bot.
    | Contoh: 'my-secret-panel-xyz', 'ops-terminal-2024', dll.
    |
    */
    'ssh_path'   => env('SSH_PATH_WEB', 'admin-ssh'),
    'log_path'   => env('LOG_PATH_WEB', 'admin-log'),
    'login_path' => env('LOGIN_PATH_WEB', 'admin-login'),

    /*
    |--------------------------------------------------------------------------
    | Keamanan Login (Brute-force Protection)
    |--------------------------------------------------------------------------
    | Pengaturan untuk melindungi halaman login dari serangan brute-force.
    |
    | max_login_attempts : Jumlah maksimum percobaan login sebelum IP dikunci.
    | lockout_minutes    : Durasi lockout dalam menit setelah melebihi batas.
    | session_timeout    : Durasi session timeout (menit) — auto-logout jika tidak ada aktivitas.
    |
    */
    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_minutes'    => env('LOCKOUT_MINUTES', 15),
    'session_timeout'    => env('SESSION_TIMEOUT_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Proteksi Pembersihan Storage (Exclusions)
    |--------------------------------------------------------------------------
    | Daftar folder di dalam storage/app/ yang dilindungi dan tidak boleh
    | dihapus atau disapu bersih oleh perintah `app:clean`.
    | Contoh: jika Anda memiliki folder 'uploads' berisi file user yang penting,
    | masukkan 'uploads' ke dalam list ini agar tidak terhapus.
    |
    */
    'clean_exclude' => [
        'uploads',
        'livewire-tmp',
        'private',
        'public',
    ],
];
