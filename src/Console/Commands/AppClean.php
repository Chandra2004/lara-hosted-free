<?php

namespace Chanzz\LaraHostedFree\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class AppClean extends Command
{
    protected $signature = 'app:clean 
        {--force : Paksa hapus tanpa konfirmasi}
        {--dry-run : Simulasi penghapusan tanpa benar-benar menghapus file}';
        
    protected $description = 'Membersihkan semua file temporary, log, dan storage untuk menghemat ruang';

    /**
     * Pencatat ukuran file yang dihapus per langkah.
     */
    private array $stats = [
        'cache'     => ['count' => 0, 'size' => 0],
        'logs'      => ['count' => 0, 'size' => 0],
        'temp'      => ['count' => 0, 'size' => 0],
        'livewire'  => ['count' => 0, 'size' => 0],
        'public'    => ['count' => 0, 'size' => 0],
        'sessions'  => ['count' => 0, 'size' => 0],
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $modeLabel = $isDryRun ? '🔍 MODE SIMULASI (DRY-RUN)' : '🧹 MODE EKSEKUSI';

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║      LARAVEL STORAGE CLEANER (ARTISAN MODE)     ║');
        $this->info('╠══════════════════════════════════════════════════╣');
        $this->info('║  ' . str_pad($modeLabel, 47) . ' ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('⚠ Tidak ada file yang akan dihapus. Hanya menampilkan preview.');
            $this->newLine();
        }

        // 1. Optimize Clear
        $this->cleanFrameworkCache($isDryRun);

        // 2. Logs
        $this->cleanLogFiles($isDryRun);

        // 3. Temporary Files in storage/app
        $this->cleanTempFiles($isDryRun);

        // 4. Livewire Trash
        $this->cleanLivewireTrash($isDryRun);

        // 5. Public Storage
        $this->cleanPublicStorage($isDryRun);

        // 6. Sessions
        $this->cleanSessions($isDryRun);

        // Ringkasan akhir
        $this->printSummary($isDryRun);
    }

    /**
     * [1/6] Membersihkan Cache Framework Laravel.
     */
    private function cleanFrameworkCache(bool $isDryRun): void
    {
        $this->comment('[1/6] Membersihkan Cache Framework...');
        
        // Hitung ukuran cache sebelum dihapus
        $cachePaths = [
            storage_path('framework/cache'),
            storage_path('framework/views'),
            base_path('bootstrap/cache'),
        ];

        foreach ($cachePaths as $cachePath) {
            if (File::isDirectory($cachePath)) {
                $this->stats['cache']['size'] += $this->getDirectorySize($cachePath);
                $this->stats['cache']['count'] += count(File::allFiles($cachePath));
            }
        }

        if (!$isDryRun) {
            try {
                Artisan::call('optimize:clear');
            } catch (\Throwable $e) {
                $this->warn('  ⚠ Gagal menjalankan optimize:clear secara penuh (kemungkinan database down), melanjutkan pembersihan file fisik.');
            }
        }

        $this->info(sprintf(
            '  ✓ Cache framework: %d file (%s) %s',
            $this->stats['cache']['count'],
            $this->formatBytes($this->stats['cache']['size']),
            $isDryRun ? '[akan dihapus]' : 'dibersihkan.'
        ));
    }

    /**
     * [2/6] Menghapus semua file log (*.log).
     */
    private function cleanLogFiles(bool $isDryRun): void
    {
        $this->comment('[2/6] Menghapus File Log...');

        $logPath = storage_path('logs');
        if (!File::isDirectory($logPath)) {
            $this->warn('  ⚠ Folder logs tidak ditemukan, dilewati.');
            return;
        }

        $logs = File::glob($logPath . '/*.log');
        foreach ($logs as $log) {
            $fileSize = File::size($log);
            $this->stats['logs']['size'] += $fileSize;
            $this->stats['logs']['count']++;

            $this->line(sprintf(
                '    - %s (%s)',
                basename($log),
                $this->formatBytes($fileSize)
            ));

            if (!$isDryRun) {
                try {
                    File::delete($log);
                } catch (\Throwable $e) {
                    $this->warn("    ⚠ Gagal menghapus " . basename($log) . ": " . $e->getMessage());
                }
            }
        }

        $this->info(sprintf(
            '  ✓ %d file log (%s) %s',
            $this->stats['logs']['count'],
            $this->formatBytes($this->stats['logs']['size']),
            $isDryRun ? '[akan dihapus]' : 'dihapus.'
        ));
    }

    /**
     * [3/6] Menghapus file & folder temporary di storage/app/.
     */
    private function cleanTempFiles(bool $isDryRun): void
    {
        $this->comment('[3/6] Menghapus File Temporary di storage/app...');

        $appPath = storage_path('app');
        if (!File::isDirectory($appPath)) {
            $this->warn('  ⚠ Folder storage/app tidak ditemukan, dilewati.');
            return;
        }

        // Hapus file temporary berdasarkan ekstensi di root storage/app
        $extensions = ['jpg', 'png', 'gif', 'webp', 'json', 'docx', 'pdf', 'pkt', 'tmp', 'bak', 'log', 'zip', 'tar', 'gz'];
        foreach ($extensions as $ext) {
            $files = File::glob($appPath . '/*.' . $ext);
            foreach ($files as $file) {
                $fileSize = File::size($file);
                $this->stats['temp']['size'] += $fileSize;
                $this->stats['temp']['count']++;

                $this->line(sprintf('    - %s (%s)', basename($file), $this->formatBytes($fileSize)));

                if (!$isDryRun) {
                    try {
                        File::delete($file);
                    } catch (\Throwable $e) {
                        $this->warn("    ⚠ Gagal menghapus " . basename($file) . ": " . $e->getMessage());
                    }
                }
            }
        }

        // Ambil folder proteksi dari konfigurasi master
        $protectedFolders = config('lara-hosted-free.clean_exclude', ['uploads', 'livewire-tmp', 'private', 'public']);

        $appDirs = File::directories($appPath);
        foreach ($appDirs as $dir) {
            $dirName = basename($dir);
            if (!in_array($dirName, $protectedFolders)) {
                $dirSize = $this->getDirectorySize($dir);
                $dirCount = count(File::allFiles($dir));
                $this->stats['temp']['size'] += $dirSize;
                $this->stats['temp']['count'] += $dirCount;

                $this->line(sprintf(
                    '    - 📁 %s/ (%d file, %s)',
                    $dirName,
                    $dirCount,
                    $this->formatBytes($dirSize)
                ));

                if (!$isDryRun) {
                    try {
                        File::deleteDirectory($dir);
                    } catch (\Throwable $e) {
                        $this->warn("    ⚠ Gagal menghapus folder " . $dirName . ": " . $e->getMessage());
                    }
                }
            } else {
                $this->line(sprintf('    - 🔒 %s/ (diproteksi, dilewati)', $dirName));
            }
        }

        $this->info(sprintf(
            '  ✓ %d item temporary (%s) %s',
            $this->stats['temp']['count'],
            $this->formatBytes($this->stats['temp']['size']),
            $isDryRun ? '[akan dihapus]' : 'dibersihkan.'
        ));
    }

    /**
     * [4/6] Membersihkan folder Livewire temporary.
     */
    private function cleanLivewireTrash(bool $isDryRun): void
    {
        $this->comment('[4/6] Menghapus Sampah Livewire...');

        $livewirePaths = [
            storage_path('app/livewire-tmp'),
            storage_path('app/private/livewire-tmp'),
        ];

        foreach ($livewirePaths as $lwPath) {
            if (!File::isDirectory($lwPath)) {
                continue;
            }

            $files = File::allFiles($lwPath);
            foreach ($files as $file) {
                if ($file->getFilename() === '.gitignore') {
                    continue;
                }
                $this->stats['livewire']['size'] += $file->getSize();
                $this->stats['livewire']['count']++;
            }

            if (!$isDryRun) {
                $this->cleanDirectoryExcludeGitignore($lwPath);
            }
        }

        $this->info(sprintf(
            '  ✓ Sampah Livewire: %d file (%s) %s',
            $this->stats['livewire']['count'],
            $this->formatBytes($this->stats['livewire']['size']),
            $isDryRun ? '[akan dihapus]' : 'dibersihkan.'
        ));
    }

    /**
     * [5/6] Membersihkan public storage (opsional, dengan konfirmasi).
     */
    private function cleanPublicStorage(bool $isDryRun): void
    {
        $this->comment('[5/6] Membersihkan Public Storage...');

        $protectedFolders = config('lara-hosted-free.clean_exclude', ['uploads', 'livewire-tmp', 'private', 'public']);

        if (in_array('public', $protectedFolders)) {
            $this->warn('  🔒 Pembersihan public storage dilewati karena diproteksi di clean_exclude.');
            return;
        }

        $publicPath = storage_path('app/public');
        if (!File::isDirectory($publicPath)) {
            $this->warn('  ⚠ Folder storage/app/public tidak ditemukan, dilewati.');
            return;
        }

        // Hitung ukuran sebelum konfirmasi
        $files = File::allFiles($publicPath);
        $previewSize = 0;
        $previewCount = 0;
        foreach ($files as $file) {
            if ($file->getFilename() !== '.gitignore') {
                $previewSize += $file->getSize();
                $previewCount++;
            }
        }

        if ($previewCount === 0) {
            $this->info('  ✓ Public storage sudah bersih.');
            return;
        }

        $this->warn(sprintf(
            '  ⚠ Akan menghapus %d file (%s) dari public storage.',
            $previewCount,
            $this->formatBytes($previewSize)
        ));

        if (!$isDryRun && !$this->option('force') && !$this->confirm('Apakah Anda yakin ingin menghapus SEMUA file di public storage?')) {
            $this->warn('  Pembersihan public storage dilewati.');
            return;
        }

        $this->stats['public']['count'] = $previewCount;
        $this->stats['public']['size'] = $previewSize;

        if (!$isDryRun) {
            $this->cleanDirectoryExcludeGitignore($publicPath);
        }

        $this->info(sprintf(
            '  ✓ Public storage: %d file (%s) %s',
            $this->stats['public']['count'],
            $this->formatBytes($this->stats['public']['size']),
            $isDryRun ? '[akan dihapus]' : 'dibersihkan.'
        ));
    }

    /**
     * [6/6] Membersihkan file session yang kedaluwarsa.
     */
    private function cleanSessions(bool $isDryRun): void
    {
        $this->comment('[6/6] Membersihkan Session yang Kedaluwarsa...');

        $sessionPath = storage_path('framework/sessions');
        if (!File::isDirectory($sessionPath)) {
            $this->warn('  ⚠ Folder sessions tidak ditemukan, dilewati.');
            return;
        }

        try {
            $files = File::allFiles($sessionPath);
            $lifetimeMinutes = (int) config('session.lifetime', 120);
            $cutoffTime = time() - ($lifetimeMinutes * 60);

            foreach ($files as $file) {
                if ($file->getFilename() === '.gitignore') {
                    continue;
                }

                $filePath = $file->getRealPath();
                try {
                    // Hanya bersihkan file session yang sudah melewati masa lifetime (tidak aktif)
                    if (File::lastModified($filePath) < $cutoffTime) {
                        $this->stats['sessions']['size'] += $file->getSize();
                        $this->stats['sessions']['count']++;

                        if (!$isDryRun) {
                            File::delete($filePath);
                        }
                    }
                } catch (\Throwable $e) {
                    // Abaikan jika gagal membaca/menghapus file session tertentu
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Gagal membaca direktori sessions: ' . $e->getMessage());
        }

        $this->info(sprintf(
            '  ✓ Session kedaluwarsa: %d file (%s) %s',
            $this->stats['sessions']['count'],
            $this->formatBytes($this->stats['sessions']['size']),
            $isDryRun ? '[akan dihapus]' : 'dihapus.'
        ));
    }

    /**
     * Menampilkan ringkasan tabel di akhir.
     */
    private function printSummary(bool $isDryRun): void
    {
        $this->newLine();

        $totalCount = 0;
        $totalSize = 0;

        $rows = [];
        $labels = [
            'cache'    => 'Cache Framework',
            'logs'     => 'File Log',
            'temp'     => 'Temporary Files',
            'livewire' => 'Livewire Trash',
            'public'   => 'Public Storage',
            'sessions' => 'Sessions',
        ];

        foreach ($this->stats as $key => $stat) {
            $totalCount += $stat['count'];
            $totalSize += $stat['size'];
            $rows[] = [
                $labels[$key],
                $stat['count'],
                $this->formatBytes($stat['size']),
            ];
        }

        $rows[] = ['─────────────────', '─────', '──────────'];
        $rows[] = ['TOTAL', $totalCount, $this->formatBytes($totalSize)];

        $this->table(
            ['Kategori', 'Jumlah', 'Ukuran'],
            $rows
        );

        $this->newLine();
        $actionLabel = $isDryRun ? 'DAPAT DIHEMAT' : 'BERHASIL DIHEMAT';
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║  ' . str_pad("✅ {$actionLabel}: {$this->formatBytes($totalSize)} ({$totalCount} file)", 49) . '║');
        $this->info('╚══════════════════════════════════════════════════╝');

        if ($isDryRun) {
            $this->newLine();
            $this->comment('Jalankan tanpa --dry-run untuk menghapus file secara permanen:');
            $this->line('  php artisan app:clean');
            $this->line('  php artisan app:clean --force');
        }
    }

    /**
     * Menghapus semua file dan subfolder dalam direktori, 
     * kecuali file .gitignore.
     */
    private function cleanDirectoryExcludeGitignore(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        try {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    try {
                        File::delete($file->getRealPath());
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal menghapus file {$file->getRealPath()}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal membaca file di {$path}: " . $e->getMessage());
        }

        try {
            $directories = File::directories($path);
            foreach ($directories as $directory) {
                try {
                    File::deleteDirectory($directory);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal menghapus folder {$directory}: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[LaraHostedFree] Gagal membaca subfolder di {$path}: " . $e->getMessage());
        }
    }

    /**
     * Menghitung total ukuran direktori secara rekursif (dalam bytes).
     */
    private function getDirectorySize(string $path): int
    {
        if (!File::isDirectory($path)) {
            return 0;
        }

        $size = 0;
        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Mengubah ukuran bytes ke format yang mudah dibaca manusia.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
