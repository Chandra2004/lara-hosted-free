<?php

namespace Chanzz\LaraHostedFree\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Durasi session timeout dalam menit (default: 30 menit).
     */
    private function getSessionTimeout(): int
    {
        return (int) (config('lara-hosted-free.session_timeout') ?: env('SESSION_TIMEOUT_MINUTES', 30));
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Fitur Logout
        if ($request->query('admin_logout') === 'true') {
            $this->destroySession();
            return redirect($request->url());
        }

        // Jika ini adalah request AJAX atau API
        if ($request->expectsJson() || $request->ajax()) {
            if (!$this->isAuthenticated()) {
                return response()->json(['error' => 'Sesi Anda telah berakhir. Silakan refresh halaman untuk login kembali.'], 401);
            }
            
            // Perbarui waktu aktivitas terakhir
            session(['admin_last_activity' => time()]);
            
            $response = $next($request);
            return $this->addSecurityHeaders($response);
        }

        // Untuk request halaman utama (GET request non-AJAX)
        // Verifikasi token satu kali pakai (One-Time Token)
        $token = $request->query('token');
        $savedToken = session('one_time_token');

        if ($token && $savedToken && hash_equals($savedToken, $token)) {
            // Token valid! Regenerate session untuk cegah session fixation
            session()->regenerate();
            session(['admin_authenticated' => true]);
            session(['admin_last_activity' => time()]);
            session()->forget('one_time_token');

            // Redirect tanpa token di URL (bersihkan dari browser history & server log)
            $cleanUrl = $this->removeQueryParam($request->fullUrl(), 'token');
            
            $response = $next($request);
            return $this->addSecurityHeaders($response);
        }

        // Jika sudah terautentikasi via session (tanpa token di URL)
        // Ini untuk navigasi setelah login awal
        if ($this->isAuthenticated()) {
            session(['admin_last_activity' => time()]);
            $response = $next($request);
            return $this->addSecurityHeaders($response);
        }

        // Tidak terautentikasi — tampilkan login form
        session(['admin_authenticated' => false]);
        
        $error = session('admin_login_error');
        $redirectTo = $this->sanitizeRedirectUrl($request->fullUrl());
        $loginPath = config('lara-hosted-free.login_path') ?: env('LOGIN_PATH_WEB', 'admin-login');
        
        $html = view('lara-hosted-free::login', [
            'error' => $error,
            'redirectTo' => $redirectTo,
            'loginPath' => $loginPath,
        ])->render();
        
        $response = response($html, 401);
        return $this->addSecurityHeaders($response);
    }

    /**
     * Cek apakah session admin masih valid (terautentikasi + belum expired).
     */
    private function isAuthenticated(): bool
    {
        if (!session('admin_authenticated', false)) {
            return false;
        }

        // Cek session timeout
        $lastActivity = session('admin_last_activity', 0);
        $timeout = $this->getSessionTimeout() * 60; // convert ke detik

        if ((time() - $lastActivity) > $timeout) {
            $this->destroySession();
            Log::info('[LaraHostedFree] Admin session expired karena inaktivitas.');
            return false;
        }

        return true;
    }

    /**
     * Hancurkan semua data session admin.
     */
    private function destroySession(): void
    {
        session()->forget('admin_authenticated');
        session()->forget('one_time_token');
        session()->forget('admin_last_activity');
        session()->regenerateToken(); // Regenerate CSRF token juga
    }

    /**
     * Sanitasi URL redirect untuk mencegah Open Redirect Attack.
     * Hanya izinkan redirect ke URL internal (same-origin).
     */
    private function sanitizeRedirectUrl(string $url): string
    {
        $sshPath = config('lara-hosted-free.ssh_path') ?: env('SSH_PATH_WEB', 'admin-ssh');
        $defaultRedirect = '/' . $sshPath;

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsed = parse_url($url);
            $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);
            if (!isset($parsed['host']) || !$appHost || strtolower($parsed['host']) !== strtolower($appHost)) {
                return $defaultRedirect;
            }
        } else {
            // Relatif URL — harus dimulai dengan / tapi bukan // atau /\ atau \ atau /\\
            if (!str_starts_with($url, '/') || str_starts_with($url, '//') || str_starts_with($url, '/\\') || str_starts_with($url, '\\') || str_starts_with($url, '/\/') || str_starts_with($url, '\/')) {
                return $defaultRedirect;
            }
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Hapus query parameter tertentu dari URL.
     */
    private function removeQueryParam(string $url, string $param): string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $params);
        unset($params[$param]);

        $cleanUrl = strtok($url, '?');
        if (!empty($params)) {
            $cleanUrl .= '?' . http_build_query($params);
        }

        return $cleanUrl;
    }

    /**
     * Tambahkan security headers ke response.
     */
    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        
        return $response;
    }
}
