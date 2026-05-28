<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Web SSH CLI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800 font-sans p-4 md:p-8 antialiased">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center">
                <svg class="w-6 h-6 mr-2 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Terminal Web SSH
            </h2>
            <a href="?admin_logout=true" class="text-sm bg-white border border-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors shadow-sm focus:ring-2 focus:ring-gray-200 outline-none">Keluar Sistem</a>
        </div>
        
        <div class="bg-gray-900 rounded-xl shadow-xl overflow-hidden border border-gray-800 flex flex-col" id="terminal-container" style="height: 70vh; min-height: 400px;">
            <!-- Output Area -->
            <div class="flex-1 p-5 overflow-y-auto font-mono text-sm whitespace-pre-wrap flex flex-col" id="terminal-output">
                <div class="text-gray-500 mb-1 select-none">Laravel Web SSH (Bypass SSH) - AJAX Enabled ⚡</div>
                <div class="text-gray-600 mb-4 select-none text-xs">Ketik "help" untuk melihat panduan. Session timeout: {{ $sessionTimeout }} menit.</div>
            </div>
            
            <!-- Input Area -->
            <div class="bg-gray-800 p-4 border-t border-gray-700">
                <div class="flex items-center font-mono text-sm">
                    <span class="text-blue-400 mr-2 select-none" id="prompt">~/app$</span>
                    <input type="text" id="cmd-input" class="flex-1 bg-transparent border-none outline-none text-gray-100 focus:ring-0 p-0" placeholder="ketik perintah di sini..." autocomplete="off" autofocus>
                    <div id="loading-indicator" class="hidden ml-2">
                        <svg class="animate-spin h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5 text-sm text-gray-500 text-center">
            💡 Tip: Gunakan <kbd class="bg-gray-200 px-1.5 py-0.5 rounded text-xs font-mono">↑</kbd> <kbd class="bg-gray-200 px-1.5 py-0.5 rounded text-xs font-mono">↓</kbd> untuk navigasi riwayat perintah. Ketik <code class="bg-gray-200 px-1.5 py-0.5 rounded text-xs font-mono">clear</code> untuk bersihkan terminal.
        </div>
    </div>

    <script>
        (() => {
            'use strict';

            const input = document.getElementById('cmd-input');
            const terminal = document.getElementById('terminal-output');
            const container = document.getElementById('terminal-container');
            const loading = document.getElementById('loading-indicator');
            const sshPath = @json($sshPath);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Command history
            const history = [];
            let historyIndex = -1;
            let isProcessing = false;

            // Built-in help text
            const HELP_TEXT = `Panduan Web SSH Terminal:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Perintah Artisan:
    php artisan list        — Lihat semua artisan command
    php artisan migrate     — Jalankan migrasi database (auto --force)
    artisan route:list      — Lihat daftar route
    app:clean               — Bersihkan cache & storage
    app:clean --dry-run     — Preview pembersihan

  Perintah Sistem:
    ls / dir                — Lihat isi direktori
    composer -v             — Cek versi composer
    git status              — Cek status git
    df -h                   — Cek penggunaan disk
    php -v                  — Cek versi PHP

  Perintah Terminal:
    clear                   — Bersihkan layar terminal
    help                    — Tampilkan panduan ini

  ⛔ Perintah yang DIBLOKIR:
    rm -rf, sudo, shutdown, reboot, mkfs, dd, 
    killall, passwd, php -r, curl|bash, dll.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`;

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function appendOutput(content, className = 'text-gray-100') {
                const div = document.createElement('div');
                div.className = `${className} mt-1 block whitespace-pre-wrap mb-4`;
                div.textContent = content;
                terminal.appendChild(div);
                terminal.scrollTop = terminal.scrollHeight;
            }

            function appendCommand(cmd) {
                const div = document.createElement('div');
                div.className = 'mb-1';
                div.innerHTML = '<span class="text-blue-400 select-none">~/app$</span> <span class="text-gray-100">' + escapeHtml(cmd) + '</span>';
                terminal.appendChild(div);
                terminal.scrollTop = terminal.scrollHeight;
            }

            function setLoading(state) {
                isProcessing = state;
                input.disabled = state;
                loading.classList.toggle('hidden', !state);
                if (!state) {
                    input.focus();
                }
            }

            // Focus input on click anywhere in terminal
            container.addEventListener('click', () => {
                if (!isProcessing) input.focus();
            });

            // Command history navigation
            input.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (history.length > 0 && historyIndex < history.length - 1) {
                        historyIndex++;
                        input.value = history[history.length - 1 - historyIndex];
                    }
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (historyIndex > 0) {
                        historyIndex--;
                        input.value = history[history.length - 1 - historyIndex];
                    } else {
                        historyIndex = -1;
                        input.value = '';
                    }
                }
            });

            // Handle form submit
            input.addEventListener('keydown', async (e) => {
                if (e.key !== 'Enter' || isProcessing) return;
                e.preventDefault();

                const cmd = input.value.trim();
                if (!cmd) return;

                // Save to history
                if (history[history.length - 1] !== cmd) {
                    history.push(cmd);
                }
                historyIndex = -1;
                input.value = '';

                // Show the command
                appendCommand(cmd);

                // Handle built-in commands
                if (cmd.toLowerCase() === 'clear') {
                    terminal.innerHTML = '';
                    return;
                }

                if (cmd.toLowerCase() === 'help') {
                    appendOutput(HELP_TEXT, 'text-green-400');
                    return;
                }

                // Send to server
                setLoading(true);

                try {
                    const formData = new FormData();
                    formData.append('command', cmd);
                    formData.append('_token', csrfToken);

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000); // 60s timeout

                    const response = await fetch('/' + sshPath + '/run', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });

                    clearTimeout(timeoutId);

                    // Coba parse sebagai JSON
                    let data = null;
                    const contentType = response.headers.get('content-type') || '';

                    if (contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        // Non-JSON response (mungkin HTML error page)
                        const text = await response.text();
                        data = { error: text ? 'Server mengembalikan respons non-JSON (status: ' + response.status + ')' : null };
                    }

                    if (response.ok) {
                        const output = (data && typeof data.output === 'string') ? data.output : 'Perintah berhasil dijalankan.';
                        appendOutput(output, 'text-gray-100');
                    } else if (response.status === 401) {
                        appendOutput('⚠ Sesi Anda telah berakhir. Halaman akan dimuat ulang dalam 3 detik...', 'text-yellow-400');
                        setTimeout(() => window.location.reload(), 3000);
                    } else if (response.status === 419) {
                        appendOutput('⚠ CSRF token expired. Halaman akan dimuat ulang...', 'text-yellow-400');
                        setTimeout(() => window.location.reload(), 2000);
                    } else if (response.status === 429) {
                        appendOutput('⚠ Terlalu banyak request. Tunggu sebentar...', 'text-yellow-400');
                    } else {
                        const errorMsg = (data && typeof data.error === 'string') ? data.error : 'Error (' + response.status + '): Terjadi kesalahan.';
                        appendOutput(errorMsg, 'text-red-400');
                    }

                } catch (error) {
                    if (error.name === 'AbortError') {
                        appendOutput('⏱ Perintah timeout setelah 60 detik. Mungkin perintah terlalu lama.', 'text-yellow-400');
                    } else {
                        appendOutput('❌ Network Error: ' + error.message, 'text-red-400');
                    }
                } finally {
                    setLoading(false);
                }
            });
        })();
    </script>
</body>
</html>
