<?php
$admin = 1;
require_once 'auth_guard.php'; // 🔐 protect admin
require_once __DIR__ . '/../db.php';
?>
<!doctype html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8" />
    <title>Remote File Upload — Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <style>
        :root {
            color-scheme: light dark;
        }
        [x-cloak] { display: none !important; }
    </style>

    <script>
        window.uploadApp = function() {
            return {
                theme: localStorage.getItem('theme') || 'light',
                fileUrl: '',
                filename: '',
                uploading: false,
                loading: false,
                recentUploads: [],
                notyf: null,

                init() {
                    this.notyf = new Notyf({
                        duration: 3000,
                        position: { x: 'right', y: 'top' }
                    });
                    this.loadRecent();
                    
                    // Auto-refresh every 5 seconds
                    setInterval(() => this.loadRecent(true), 5000);
                },

                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                },

                themeLabel() {
                    return this.theme === 'light' ? '🌙 Dark' : '☀️ Light';
                },

                async uploadFile() {
                    if (!this.fileUrl) {
                        this.notyf.error('Please enter a file URL');
                        return;
                    }

                    this.uploading = true;

                    try {
                        const response = await fetch('/', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-API-KEY': '<?php echo env("API_ACCESS_KEY"); ?>'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                url: this.fileUrl,
                                filename: this.filename || undefined
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.notyf.success('Upload started! File: ' + data.key);
                            this.clearForm();
                            setTimeout(() => this.loadRecent(), 1000);
                        } else {
                            this.notyf.error(data.error || 'Upload failed');
                        }
                    } catch (error) {
                        this.notyf.error('Network error: ' + error.message);
                    } finally {
                        this.uploading = false;
                    }
                },

                clearForm() {
                    this.fileUrl = '';
                    this.filename = '';
                },

                async loadRecent(silent = false) {
                    if (!silent) this.loading = true;

                    try {
                        const response = await fetch('/admin/list_uploads.php?limit=10&sort[created_at]=desc', {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const data = await response.json();

                        if (data.data) {
                            this.recentUploads = data.data || [];
                        } else {
                            if (!silent) {
                                this.notyf.error(data.error || 'Failed to load recent uploads');
                            }
                        }
                    } catch (error) {
                        if (!silent) {
                            this.notyf.error('Failed to load recent uploads');
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                statusBadge(status) {
                    const badges = {
                        'pending': '<span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">⏳ Pending</span>',
                        'completed': '<span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">✅ Completed</span>',
                        'failed': '<span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">❌ Failed</span>'
                    };
                    return badges[status] || status;
                },

                formatBytes(bytes) {
                    if (bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
                },

                copyUrl(url) {
                    navigator.clipboard.writeText(url).then(() => {
                        this.notyf.success('URL copied to clipboard!');
                    }).catch(() => {
                        this.notyf.error('Failed to copy URL');
                    });
                }
            }
        }
    </script>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="min-h-full bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100 transition-colors" x-data="uploadApp()" x-init="init()" :data-theme="theme" x-cloak>

    <div class="max-w-4xl mx-auto p-4">
        <div class="flex items-center justify-between gap-3 mb-6">
            <h1 class="text-2xl font-bold">📤 Remote File Upload</h1>
            <div class="flex items-center gap-2">
                <a href="/admin" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">← Back to Dashboard</a>
                <button @click="toggleTheme()" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700">
                    <span x-text="themeLabel()"></span>
                </button>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Upload Remote File</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">File URL *</label>
                    <input 
                        type="url" 
                        x-model="fileUrl" 
                        placeholder="https://example.com/file.jpg"
                        class="w-full px-3 py-2 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        @keyup.enter="uploadFile()"
                    />
                    <p class="text-xs text-slate-500 mt-1">Enter the direct URL to the file you want to upload</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Custom Filename (optional)</label>
                    <input 
                        type="text" 
                        x-model="filename" 
                        placeholder="my-file.jpg"
                        class="w-full px-3 py-2 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        @keyup.enter="uploadFile()"
                    />
                    <p class="text-xs text-slate-500 mt-1">Leave empty to use the original filename from URL</p>
                </div>

                <div class="flex gap-3">
                    <button 
                        @click="uploadFile()" 
                        :disabled="uploading || !fileUrl"
                        class="flex-1 px-4 py-3 rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 font-medium"
                    >
                        <svg x-show="uploading" class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                        </svg>
                        <span x-text="uploading ? 'Uploading...' : '📤 Upload File'"></span>
                    </button>
                    <button 
                        @click="clearForm()" 
                        :disabled="uploading"
                        class="px-4 py-3 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Uploads -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">Recent Uploads</h2>
                <button @click="loadRecent()" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm">
                    🔄 Refresh
                </button>
            </div>

            <div x-show="loading" class="text-center py-8 text-slate-500">
                <div class="animate-spin inline-block h-8 w-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
                <p class="mt-2">Loading...</p>
            </div>

            <div x-show="!loading && recentUploads.length === 0" class="text-center py-8 text-slate-500">
                No recent uploads found
            </div>

            <div x-show="!loading && recentUploads.length > 0" class="space-y-3">
                <template x-for="upload in recentUploads" :key="upload.id">
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800/30">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-medium truncate" x-text="upload.object_key"></span>
                                    <span x-html="statusBadge(upload.status)"></span>
                                </div>
                                <div class="text-xs text-slate-500 space-y-1">
                                    <div x-show="upload.original_url" class="truncate">
                                        URL: <span x-text="upload.original_url"></span>
                                    </div>
                                    <div x-show="upload.message">
                                        <span x-text="upload.message"></span>
                                    </div>
                                    <div class="flex gap-4">
                                        <span x-show="upload.size_bytes > 0">Size: <span x-text="formatBytes(upload.size_bytes)"></span></span>
                                        <span>Created: <span x-text="upload.created_at"></span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <template x-if="upload.status === 'completed' && upload.file_url">
                                    <a :href="upload.file_url" target="_blank" 
                                        class="px-3 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 text-sm whitespace-nowrap">
                                        Open File
                                    </a>
                                </template>
                                <template x-if="upload.status === 'completed' && upload.file_url">
                                    <button @click="copyUrl(upload.file_url)" 
                                        class="px-3 py-1.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm">
                                        📋 Copy
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</body>

</html>
