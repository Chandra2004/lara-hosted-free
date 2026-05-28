<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Chanzz\LaraHostedFree\Http\Middleware\AdminAuth;
use Symfony\Component\Process\Process;



$sshPath = config('lara-hosted-free.ssh_path') ?: env('SSH_PATH_WEB', 'admin-ssh');

Route::middleware(['web'])->group(function () use ($sshPath) {

    $loginPath = config('lara-hosted-free.login_path') ?: env('LOGIN_PATH_WEB', 'admin-login');

    Route::post('/' . $loginPath, function (Request $request) use ($sshPath) {
        $ip = $request->ip();

        // Honeypot check — jika field tersembunyi diisi, ini adalah bot
        if ($request->filled('website_url') || $request->filled('phone_number')) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Bot terdeteksi via honeypot dari IP: {$ip}");
            // Beri respons normal agar bot tidak tahu sudah terdeteksi
            $safeRedirect = safeRedirectUrl($request->input('redirect_to', '/' . $sshPath), $sshPath);
            return redirect($safeRedirect)->with('admin_login_error', 'Username atau Password salah!');
        }

        $lockoutKey = 'admin_lockout_' . md5($ip);
        $attemptsKey = 'admin_attempts_' . md5($ip);
        $maxAttempts = (int) (config('lara-hosted-free.max_login_attempts') ?: env('MAX_LOGIN_ATTEMPTS', 5));
        $lockoutMinutes = (int) (config('lara-hosted-free.lockout_minutes') ?: env('LOCKOUT_MINUTES', 15));

        // Cek apakah IP sedang di-lockout
        if (safeCacheHas($lockoutKey)) {
            $remainingSeconds = safeCacheGet($lockoutKey) - time();
            $remainingMinutes = ceil($remainingSeconds / 60);
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] IP {$ip} mencoba login saat masih terkunci.");
            $safeRedirect = safeRedirectUrl($request->input('redirect_to', '/' . $sshPath), $sshPath);
            return redirect($safeRedirect)->with('admin_login_error', "Akun terkunci sementara. Coba lagi dalam {$remainingMinutes} menit.");
        }

        $validUser = trim(config('lara-hosted-free.username') ?: env('USERNAME_ADMIN', 'admin'), "'\"");
        $hashedPass = trim(config('lara-hosted-free.password') ?: env('PASSWORD_ADMIN', '$2y$12$fx61lAuR2mpQZMtJ84WA3OnG/zgBVEIJho6C7Fcc8AKr8NnFz68pa'), "'\"");
        
        if (trim($request->admin_username) === $validUser && \Illuminate\Support\Facades\Hash::check($request->admin_password, $hashedPass)) {
            // Login berhasil — reset attempts & regenerate session
            safeCacheForget($attemptsKey);
            safeCacheForget($lockoutKey);
            session()->regenerate();

            // Generate One-Time Token
            $token = \Illuminate\Support\Str::random(64);
            session(['one_time_token' => $token]);
            
            \Illuminate\Support\Facades\Log::info("[LaraHostedFree] Login berhasil dari IP: {$ip}");

            $redirectUrl = safeRedirectUrl($request->input('redirect_to', '/' . $sshPath), $sshPath);
            
            // Bersihkan jika parameter token lama sudah ada di redirectUrl
            if (str_contains($redirectUrl, 'token=')) {
                $redirectUrl = preg_replace('/([?&])token=[^&]+(&?)/', '$1', $redirectUrl);
                $redirectUrl = rtrim($redirectUrl, '?&');
            }
            
            $redirectUrlWithToken = $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'token=' . $token;
            return redirect($redirectUrlWithToken);
        }
        
        // Login gagal — catat percobaan
        $attempts = (int) safeCacheGet($attemptsKey, 0) + 1;
        safeCachePut($attemptsKey, $attempts, $lockoutMinutes);

        \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Login gagal (percobaan ke-{$attempts}) dari IP: {$ip}, User-Agent: {$request->userAgent()}");

        // Lockout jika melebihi batas
        if ($attempts >= $maxAttempts) {
            safeCachePut($lockoutKey, time() + ($lockoutMinutes * 60), $lockoutMinutes);
            \Illuminate\Support\Facades\Log::critical("[LaraHostedFree] IP {$ip} TERKUNCI setelah {$maxAttempts}x percobaan login gagal.");
            $safeRedirect = safeRedirectUrl($request->input('redirect_to', '/' . $sshPath), $sshPath);
            return redirect($safeRedirect)->with('admin_login_error', "Terlalu banyak percobaan login. Akun dikunci selama {$lockoutMinutes} menit.");
        }

        $remaining = $maxAttempts - $attempts;
        $safeRedirect = safeRedirectUrl($request->input('redirect_to', '/' . $sshPath), $sshPath);
        return redirect($safeRedirect)->with('admin_login_error', "Username atau Password salah! ({$remaining} percobaan tersisa)");
    })->middleware('throttle:10,1');

    Route::middleware([AdminAuth::class])->group(function () use ($sshPath) {
        
        Route::get('/' . $sshPath, function () use ($sshPath) {
            if (!(config('lara-hosted-free.artisan_web') ?? env('WEB_CONFIG', true))) {
                abort(403, 'Akses Web SSH Dinonaktifkan.');
            }
            
            return view('lara-hosted-free::terminal', [
                'sshPath' => $sshPath,
                'sessionTimeout' => (int) (config('lara-hosted-free.session_timeout') ?: env('SESSION_TIMEOUT_MINUTES', 30)),
            ]);
        });

        Route::post('/' . $sshPath . '/run', function (Request $request) {
            if (!(config('lara-hosted-free.artisan_web') ?? env('WEB_CONFIG', true))) {
                return response()->json(['error' => 'Akses Web SSH Dinonaktifkan.'], 403);
            }

            $cmd = trim($request->input('command'));
            if (empty($cmd)) {
                return response()->json(['output' => '']);
            }

            // Audit logging: Catat semua eksekusi perintah admin
            \Illuminate\Support\Facades\Log::info("[LaraHostedFree] IP {$request->ip()} mengeksekusi perintah Web SSH: {$cmd}");

            // Hindari perintah interaktif yang bisa membuat hosting hang
            if (preg_match('/^(tinker|serve|pail|nano|vim|vi|interactive)\b/', $cmd)) {
                return response()->json(['output' => "Perintah interaktif seperti '$cmd' tidak diizinkan di Web SSH karena akan membuat server hang."]);
            }

            // Blokir perintah sistem yang berbahaya / destruktif
            $dangerousPatterns = [
                '/\brm\s+(-[a-zA-Z]*f|-[a-zA-Z]*r|--force|--recursive)\b/i',   // rm -rf, rm -f, rm --force
                '/\bmkfs\b/i',                                                    // format filesystem
                '/\bdd\s+if=/i',                                                  // disk destroyer
                '/\b(shutdown|reboot|poweroff|init\s+[0-6])\b/i',                // system power
                '/\b(chmod\s+(-R\s+)?[0-7]*7[0-7]*\s+\/|chown\s+-R)\b/i',       // recursive permission change on root
                '/\bkillall\b/i',                                                 // kill semua proses
                '/\b(passwd|useradd|userdel|usermod)\b/i',                        // user management
                '/>\s*\/etc\//i',                                                 // overwrite system config
                '/\bsudo\b/i',                                                    // escalate privilege
                '/\bcurl\b.*\|\s*(bash|sh)\b/i',                                 // pipe curl to shell
                '/\bwget\b.*\|\s*(bash|sh)\b/i',                                 // pipe wget to shell
                '/\b(nc|ncat|netcat)\s+-[a-zA-Z]*l/i',                           // reverse shell / listener
                '/\bphp\s+-r\b/i',                                                // arbitrary PHP execution
                '/\b(:>\s*|truncate\s+-s\s*0)\//i',                               // truncate system files
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $cmd)) {
                    return response()->json(['output' => "⛔ Perintah '$cmd' diblokir karena terdeteksi sebagai perintah berbahaya/destruktif."]);
                }
            }

            try {
                $cwd = base_path();
                
                // Cek apakah ini perintah artisan
                $isArtisan = false;
                $artisanCmd = $cmd;
                
                if (str_starts_with($cmd, 'php artisan ')) {
                    $isArtisan = true;
                    $artisanCmd = substr($cmd, 12);
                } elseif (str_starts_with($cmd, 'artisan ')) {
                    $isArtisan = true;
                    $artisanCmd = substr($cmd, 8);
                }
                
                if ($isArtisan) {
                    $artisanCmd = trim($artisanCmd);
                    
                    if ($artisanCmd === '' || $artisanCmd === 'list') {
                        Artisan::call('list');
                        $output = Artisan::output();
                    } elseif ($artisanCmd === '--version' || $artisanCmd === '-V') {
                        $output = "Laravel Framework " . app()->version();
                    } else {
                        // Tambahkan --force otomatis pada perintah yang membutuhkannya
                        if (str_starts_with($artisanCmd, 'migrate') && !str_contains($artisanCmd, '--force')) {
                            $artisanCmd .= ' --force';
                        }

                        // Tambahkan --no-ansi agar output bersih dari color code
                        if (!str_contains($artisanCmd, '--no-ansi') && !str_contains($artisanCmd, '--ansi')) {
                            $artisanCmd .= ' --no-ansi';
                        }
                        
                        // Eksekusi via Laravel Internal Artisan (Cepat & Stabil)
                        Artisan::call($artisanCmd);
                        $output = Artisan::output();
                    }
                } else {
                    // Eksekusi sebagai System Command menggunakan Symfony Process (lebih aman, asinkron, bebas deadlock)
                    try {
                        $process = Process::fromShellCommandline($cmd, $cwd);
                        $process->setTimeout(60); // 60 detik timeout tingkat PHP
                        $process->run();

                        $output = $process->getOutput();
                        $errorOutput = $process->getErrorOutput();
                        if (!empty($errorOutput)) {
                            $output .= (empty($output) ? '' : "\n") . $errorOutput;
                        }
                    } catch (\Throwable $e) {
                        if (str_contains(strtolower($e->getMessage()), 'proc_open')) {
                            // Fallback untuk basic commands jika proc_open didisable
                            $baseCmd = strtolower(explode(' ', trim($cmd))[0]);
                            if ($baseCmd === 'ls' || $baseCmd === 'dir') {
                                $files = scandir($cwd);
                                $output = implode("\n", $files);
                            } elseif ($baseCmd === 'pwd') {
                                $output = $cwd;
                            } elseif ($baseCmd === 'php' && (str_contains($cmd, '-v') || str_contains($cmd, '--version'))) {
                                $output = "PHP " . phpversion();
                            } else {
                                $output = "Gagal mengeksekusi perintah sistem. Fungsi 'proc_open' dinonaktifkan (disable_functions) di php.ini hosting Anda.\n\n(Hanya perintah Artisan atau fallback perintah dasar seperti 'ls', 'dir', 'pwd', 'php -v' yang bisa berjalan)";
                            }
                        } else {
                            $output = "Gagal mengeksekusi perintah: " . $e->getMessage();
                        }
                    }
                }

                // Strip ANSI color/format codes agar tampil bersih di terminal web
                $output = preg_replace('/\e\[[0-9;]*[mGKHF]/u', '', (string) $output);
                $output = preg_replace('/\e\[[0-9;]*[A-Za-z]/u', '', $output);

                if (empty(trim($output))) {
                    $output = "✓ Perintah berhasil dieksekusi (tanpa output).";
                }
            } catch (\Throwable $e) {
                $output = "ERROR:\n" . $e->getMessage();
            }

            return response()->json(['output' => trim($output)]);
        });

    });

});
