<?php

if (!function_exists('safeCacheGet')) {
    function safeCacheGet(string $key, $default = null) {
        try {
            return cache()->get($key, $default);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal mengakses cache (kemungkinan database down): " . $e->getMessage());
            return session()->get($key, $default);
        }
    }
}

if (!function_exists('safeCachePut')) {
    function safeCachePut(string $key, $value, $minutes) {
        try {
            cache()->put($key, $value, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal menulis ke cache (kemungkinan database down): " . $e->getMessage());
            session()->put($key, $value);
        }
    }
}

if (!function_exists('safeCacheHas')) {
    function safeCacheHas(string $key) {
        try {
            return cache()->has($key);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal memeriksa cache (kemungkinan database down): " . $e->getMessage());
            return session()->has($key);
        }
    }
}

if (!function_exists('safeCacheForget')) {
    function safeCacheForget(string $key) {
        try {
            cache()->forget($key);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal menghapus cache (kemungkinan database down): " . $e->getMessage());
            session()->forget($key);
        }
    }
}

if (!function_exists('safeRedirectUrl')) {
    function safeRedirectUrl(string $url, string $sshPath): string {
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
        return $url;
    }
}
