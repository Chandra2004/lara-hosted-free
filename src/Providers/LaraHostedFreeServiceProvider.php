<?php

namespace Chanzz\LaraHostedFree\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class LaraHostedFreeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/lara-hosted-free.php', 'lara-hosted-free');

        $this->app->bind('path.public', function () {
            $path = config('lara-hosted-free.public_path');
            if (empty($path)) {
                $path = env('PUBLIC_PATH', 'public');
            }
            if (empty($path)) {
                $path = 'public';
            }
            // Jika path diawali slash, itu adalah absolute path — gunakan langsung
            // Jika path relatif (misal: '../htdocs' atau 'public_html'), resolve dari base_path
            return str_starts_with($path, '/') ? $path : base_path($path);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config file & register console commands
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/lara-hosted-free.php' => config_path('lara-hosted-free.php'),
            ], 'lara-hosted-config');

            $this->publishes([
                __DIR__.'/../../.htaccess' => base_path('.htaccess'),
            ], 'lara-hosted-htaccess');

            $this->commands([
                \Chanzz\LaraHostedFree\Console\Commands\AppClean::class,
            ]);
        }

        // Load package blade views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'lara-hosted-free');

        // Load rute khusus Web SSH dari dalam package
        $this->loadRoutesFrom(__DIR__.'/../../routes/ssh.php');

        // Bypassing Gate bawaan opcodesio/log-viewer agar keamanan sepenuhnya ditangani oleh middleware
        Gate::define('viewLogViewer', function ($user = null) {
            return true;
        });
    }
}
