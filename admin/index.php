<?php
$admin = 1;
require_once 'auth_guard.php'; // 🔐 protect admin
?>
<!doctype html>
<html lang="en" x-data="uploadsApp()" x-init="init()" :data-theme="theme" class="h-full">

<head>
    <meta charset="utf-8" />
    <title>R2 Uploads — Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <style>
        :root {
            color-scheme: light dark;
        }

        [data-theme="dark"] .row-completed {
            background-color: #0f2b1b;
        }

        [data-theme="dark"] .row-pending {
            background-color: #2e2a17;
        }

        [data-theme="dark"] .row-failed {
            background-color: #311a1a;
        }

        [data-theme="light"] .row-completed {
            background-color: #e8f7ef;
        }

        [data-theme="light"] .row-pending {
            background-color: #fff7e0;
        }

        [data-theme="light"] .row-failed {
            background-color: #ffeaea;
        }
    </style>
</head>

<body class="min-h-full bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100 transition-colors">

    <div class="max-w-7xl mx-auto p-4">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h1 class="text-2xl font-bold">📦 R2 Uploads — Admin</h1>
            <div class="flex items-center gap-2">
                <a href="/admin/upload.php" class="px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Upload
                </a>
                <a href="/admin/logout.php" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Logout</a>
                <button @click="toggleTheme()" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span x-text="themeLabel()"></span>
                </button>
                <button @click="refresh()" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Refresh</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
            <div class="flex gap-2">
                <input type="text" x-model="search" @keyup.enter="goPage(1)"
                    placeholder="Search filename / URL / message / id"
                    class="w-full px-3 py-2 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800" />
                <select x-model="status" @change="goPage(1)"
                    class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div class="flex items-center gap-2 justify-start md:justify-end">
                <!-- 🔁 Retry -->
                <button @click="bulkRetry()" class="px-3 py-2 rounded border border-amber-400 text-amber-700 dark:text-amber-300">
                    Retry
                </button>

                <!-- 🗑️ Delete -->
                <button id="deleteBtn" @click="bulkDelete()" :disabled="deleting"
                    class="px-3 py-2 rounded border border-red-400 text-red-600 dark:text-red-300 disabled:opacity-50 flex items-center gap-2">
                    <svg x-show="deleting" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                    </svg>
                    <span x-text="deleting ? 'Deleting…' : 'Delete'"></span>
                </button>

                <!-- 📋 Copy Links -->
                <button @click="copyLinks()" class="px-3 py-2 rounded border border-emerald-400 text-emerald-700 dark:text-emerald-300">
                    Copy Links
                </button>
            </div>
        </div>

        <!-- table ... (unchanged body from previous version) -->
        <!-- BEGIN TABLE -->
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="p-2 w-10"><input type="checkbox" @change="toggleAll($event)" /></th>
                        <th class="p-2 w-16 cursor-pointer select-none" @click="sortBy('id')">
                            ID <span x-show="sort.field==='id'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none" @click="sortBy('object_key')">
                            Filename <span x-show="sort.field==='object_key'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none" @click="sortBy('status')">
                            Status <span x-show="sort.field==='status'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none text-right" @click="sortBy('size_bytes')">
                            Size <span x-show="sort.field==='size_bytes'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none text-center" @click="sortBy('retries')">
                            Retries <span x-show="sort.field==='retries'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none" @click="sortBy('created_at')">
                            Created <span x-show="sort.field==='created_at'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2 cursor-pointer select-none" @click="sortBy('updated_at')">
                            Updated <span x-show="sort.field==='updated_at'">(<span x-text="sort.dir"></span>)</span>
                        </th>
                        <th class="p-2">Open</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="9" class="p-4 text-center text-slate-500">Loading…</td>
                        </tr>
                    </template>
                    <template x-if="!loading && rows.length===0">
                        <tr>
                            <td colspan="9" class="p-4 text-center text-slate-500">No uploads found</td>
                        </tr>
                    </template>
                    <template x-for="r in rows" :key="r.id">
                        <tr :class="rowClass(r)" class="border-t border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="p-2"><input type="checkbox" :value="r.object_key" x-model="selected" /></td>
                            <td class="p-2 text-center" x-text="r.id"></td>
                            <td class="p-2">
                                <div class="font-medium" x-text="filenameOf(r.object_key)"></div>
                                <div class="text-xs text-slate-500 break-all" x-text="r.object_key"></div>
                            </td>
                            <td class="p-2">
                                <span x-html="statusBadge(r.status)"></span>
                                <template x-if="r.message">
                                    <div class="text-xs text-slate-500" x-text="r.message"></div>
                                </template>
                            </td>
                            <td class="p-2 text-right" x-text="sizeHuman(r.size_bytes)"></td>
                            <td class="p-2 text-center" x-text="(r.retries ?? 0)"></td>
                            <td class="p-2" x-text="r.created_at"></td>
                            <td class="p-2" x-text="r.updated_at"></td>
                            <td class="p-2 text-center">
                                <template x-if="r.status==='completed' && r.file_url">
                                    <a :href="r.file_url" target="_blank" class="text-blue-600 dark:text-blue-300 hover:underline">🔗</a>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- END TABLE -->

        <div class="flex items-center justify-between mt-3">
            <div class="text-sm text-slate-500">
                Page <span x-text="page"></span> / <span x-text="last_page"></span> — Total <span x-text="total"></span>
            </div>
            <div class="flex items-center gap-2">
                <button @click="prevPage()" :disabled="page<=1"
                    class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 disabled:opacity-50">Prev</button>
                <button @click="nextPage()" :disabled="page>=last_page"
                    class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 disabled:opacity-50">Next</button>
                <select x-model.number="limit" @change="goPage(1)"
                    class="px-2 py-2 rounded border border-slate-300 dark:border-slate-700">
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                    <option :value="150">150</option>
                    <option :value="200">200</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Error modal reused (unchanged) -->
    <div x-show="showErrors" style="display:none" class="fixed inset-0 bg-black/50 z-50" @click.self="showErrors=false">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg max-w-2xl mx-auto mt-24 p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-semibold">Delete Errors</h3>
                <button class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" @click="showErrors=false">✖</button>
            </div>
            <div class="max-h-[60vh] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="text-left p-2">File</th>
                            <th class="text-left p-2">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="e in errorList" :key="e.key">
                            <tr class="border-t border-slate-100 dark:border-slate-700">
                                <td class="p-2 break-all" x-text="e.key"></td>
                                <td class="p-2" x-text="e.error || 'Unknown error'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="text-right mt-3">
                <button class="px-3 py-2 rounded bg-blue-600 text-white" @click="showErrors=false">Close</button>
            </div>
        </div>
    </div>

    <script>
        function uploadsApp() {
            return {
                theme: (localStorage.getItem('r2_theme') || 'auto'),
                loading: false,
                deleting: false,
                rows: [],
                selected: [],
                showErrors: false,
                errorList: [],
                page: 1,
                limit: 50,
                last_page: 1,
                total: 0,
                search: '',
                status: '',
                sort: {
                    field: 'id',
                    dir: 'desc'
                },
                notyf: null,

                init() {
                    this.applyTheme();
                    this.fetchRows();
                    this.notyf = new Notyf({
                        duration: 3500,
                        ripple: true
                    });
                },
                themeLabel() {
                    return this.theme === 'auto' ? 'Auto Theme' : (this.theme === 'dark' ? 'Dark' : 'Light');
                },
                toggleTheme() {
                    const n = this.theme === 'auto' ? 'dark' : (this.theme === 'dark' ? 'light' : 'auto');
                    this.theme = n;
                    localStorage.setItem('r2_theme', n);
                    this.applyTheme();
                },
                applyTheme() {
                    if (this.theme === 'auto') document.documentElement.removeAttribute('data-theme');
                    else document.documentElement.setAttribute('data-theme', this.theme);
                },
                filenameOf(key) {
                    try {
                        return key.split('/').pop();
                    } catch (e) {
                        return key;
                    }
                },
                statusBadge(s) {
                    if (s === 'completed') return '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-green-600 text-white">✔ completed</span>';
                    if (s === 'failed') return '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-red-600 text-white">✖ failed</span>';
                    return '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-amber-400 text-slate-900">⏳ pending</span>';
                },
                rowClass(r) {
                    if (r.status === 'completed') return 'row-completed';
                    if (r.status === 'failed') return 'row-failed';
                    return 'row-pending';
                },
                sizeHuman(b) {
                    const n = Number(b || 0);
                    if (!n) return '-';
                    const u = ['B', 'KB', 'MB', 'GB', 'TB'];
                    let i = 0,
                        v = n;
                    while (v >= 1024 && i < u.length - 1) {
                        v /= 1024;
                        i++;
                    }
                    return (v >= 10 || i === 0 ? v.toFixed(0) : v.toFixed(1)) + ' ' + u[i];
                },

                queryParams() {
                    const p = new URLSearchParams();
                    p.set('page', this.page);
                    p.set('limit', this.limit);
                    if (this.search) p.set('q', this.search);
                    if (this.status !== '') p.set('status', this.status);
                    p.set(`sort[${this.sort.field}]`, this.sort.dir);
                    return p.toString();
                },

                async fetchRows() {
                    this.loading = true;
                    try {
                        const res = await fetch('list_uploads.php?' + this.queryParams(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        if (res.status === 401) {
                            window.location.href = '/admin/login.php';
                            return;
                        }
                        const json = await res.json();
                        this.rows = json.data || [];
                        this.total = json.total || 0;
                        this.last_page = json.last_page || 1;
                    } catch (e) {
                        console.error(e);
                        this.rows = [];
                    } finally {
                        this.loading = false;
                    }
                },

                goPage(n) {
                    this.page = n;
                    this.selected = [];
                    this.fetchRows();
                },
                nextPage() {
                    if (this.page < this.last_page) this.goPage(this.page + 1);
                },
                prevPage() {
                    if (this.page > 1) this.goPage(this.page - 1);
                },
                refresh() {
                    this.fetchRows();
                },

                sortBy(field) {
                    if (this.sort.field === field) this.sort.dir = (this.sort.dir === 'asc') ? 'desc' : 'asc';
                    else {
                        this.sort.field = field;
                        this.sort.dir = 'asc';
                    }
                    this.goPage(1);
                },

                toggleAll(ev) {
                    this.selected = ev.target.checked ? this.rows.map(r => r.object_key) : [];
                },

                async bulkDelete() {
                    if (this.selected.length === 0) return alert('Select rows first.');
                    if (!confirm(`Delete ${this.selected.length} item(s) from DB + R2?`)) return;
                    this.deleting = true;
                    try {
                        const res = await fetch('bulk_delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                keys: this.selected,
                                mode: 'db+r2'
                            })
                        });
                        if (res.status === 401) {
                            window.location.href = '/admin/login.php';
                            return;
                        }
                        const json = await res.json();
                        if (!json.success) {
                            this.notyf.error(json.error || 'Delete failed');
                            return;
                        }
                        const dDb = json.deleted_db || 0,
                            dR2 = json.deleted_r2 || 0,
                            errs = Array.isArray(json.errors) ? json.errors : [];
                        if (dDb === 0 && dR2 === 0 && errs.length) {
                            this.notyf.error('Failed to delete selected files');
                            this.errorList = errs;
                            this.showErrors = true;
                        } else if (errs.length) {
                            const toast = this.notyf.open({
                                type: 'warning',
                                message: `${dDb} DB / ${dR2} R2 deleted — ${errs.length} failed (click to view)`,
                                background: '#f59e0b',
                                duration: 4000
                            });
                            toast.on('click', () => {
                                this.errorList = errs;
                                this.showErrors = true;
                            });
                        } else {
                            this.notyf.success(`Deleted ${dDb} DB / ${dR2} R2`);
                        }
                        this.selected = [];
                        this.refresh();
                    } catch (e) {
                        this.notyf.error(e.message || 'Delete request error');
                    } finally {
                        this.deleting = false;
                    }
                },

                async bulkRetry() {
                    if (this.selected.length === 0) return alert('Select rows first.');
                    if (!confirm(`Retry ${this.selected.length} failed/pending uploads?`)) return;
                    try {
                        const res = await fetch('bulk_retry.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                keys: this.selected
                            })
                        });
                        if (res.status === 401) {
                            window.location.href = '/admin/login.php';
                            return;
                        }
                        const json = await res.json();
                        if (!json.success) throw new Error(json.error || 'Retry failed');
                        const ok = json.results.filter(x => x.status === 'queued').length;
                        const skipped = json.results.length - ok;
                        if (ok && !skipped) this.notyf.success(`Queued ${ok} uploads`);
                        else if (ok && skipped) this.notyf.open({
                            type: 'warning',
                            message: `Queued ${ok}, skipped ${skipped}`,
                            background: '#f59e0b'
                        });
                        else this.notyf.error('No uploads queued');
                        this.selected = [];
                        this.refresh();
                    } catch (e) {
                        this.notyf.error(e.message || 'Retry error');
                    }
                },

                async copyLinks() {
                    if (this.selected.length === 0) {
                        this.notyf.error('Select rows first');
                        return;
                    }
                    // Map selected keys -> find rows, include only completed with file_url
                    const map = new Map(this.rows.map(r => [r.object_key, r]));
                    const urls = this.selected
                        .map(k => map.get(k))
                        .filter(r => r && r.status === 'completed' && r.file_url)
                        .map(r => r.file_url);

                    if (urls.length === 0) {
                        this.notyf.open({
                            type: 'warning',
                            message: 'No completed files in selection'
                        });
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(urls.join('\n'));
                        this.notyf.success(`Copied ${urls.length} link(s)`);
                    } catch (e) {
                        // Fallback for older browsers
                        const ta = document.createElement('textarea');
                        ta.value = urls.join('\n');
                        document.body.appendChild(ta);
                        ta.select();
                        try {
                            document.execCommand('copy');
                            this.notyf.success(`Copied ${urls.length} link(s)`);
                        } catch (err) {
                            this.notyf.error('Copy failed');
                        } finally {
                            document.body.removeChild(ta);
                        }
                    }
                },
            }
        }
    </script>
</body>

</html>