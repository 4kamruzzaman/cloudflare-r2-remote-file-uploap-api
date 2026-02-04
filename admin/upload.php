<?php
$admin = 1;
require_once 'auth_guard.php'; // 🔐 protect admin
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db.php';

// Handle direct file upload (local file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json');

    try {
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];
            throw new Exception($errorMessages[$file['error']] ?? 'Unknown upload error');
        }

        // Get custom filename or use original
        $customFilename = !empty($_POST['filename']) ? trim($_POST['filename']) : $file['name'];
        $objectKey = basename($customFilename); // Enforce flat key

        // Get file info
        $tmpPath = $file['tmp_name'];
        $sizeBytes = filesize($tmpPath);

        // Determine MIME type
        $mimeType = getMimeTypeFromFilename($objectKey);

        // Set initial status
        setStatus($objectKey, 'pending', null, 'Starting upload...', 0, $sizeBytes, 'local://upload', 0, 0);

        // Upload to R2
        $s3 = r2();
        $bucket = env('R2_BUCKET');

        // Delete existing if present
        try {
            $s3->deleteObject(['Bucket' => $bucket, 'Key' => $objectKey]);
        } catch (Throwable $t) {
        }

        $t0 = microtime(true);

        // For small files, use putObject; for large files, use multipart
        $partSizeMb = max(5, (int)env('R2_PART_SIZE_MB', 32));
        $partSize = $partSizeMb * 1024 * 1024;

        if ($sizeBytes < $partSize) {
            // Simple upload for small files
            $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $objectKey,
                'SourceFile' => $tmpPath,
                'ContentType' => $mimeType,
                'ContentDisposition' => 'attachment',
                'ACL' => 'public-read',
            ]);
        } else {
            // Multipart upload for large files
            $uploader = new \Aws\S3\MultipartUploader($s3, $tmpPath, [
                'bucket' => $bucket,
                'key' => $objectKey,
                'part_size' => $partSize,
                'concurrency' => 4,
                'before_initiate' => function ($params) use ($mimeType) {
                    $params['ContentType'] = $mimeType;
                    $params['ContentDisposition'] = 'attachment';
                    $params['ACL'] = 'public-read';
                    return $params;
                }
            ]);
            $uploader->upload();
        }

        $uploadTimeSec = (int)round(microtime(true) - $t0);

        // Verify upload
        $head = $s3->headObject(['Bucket' => $bucket, 'Key' => $objectKey]);
        $remoteSize = (int)($head['ContentLength'] ?? 0);

        if ($remoteSize !== $sizeBytes) {
            r2_delete($objectKey);
            throw new Exception('Upload verification failed: size mismatch');
        }

        $fileUrl = r2_url($objectKey);
        setStatus($objectKey, 'completed', $fileUrl, 'Uploaded successfully', 0, $sizeBytes, 'local://upload', 0, $uploadTimeSec);

        echo json_encode([
            'success' => true,
            'key' => $objectKey,
            'file_url' => $fileUrl,
            'size_bytes' => $sizeBytes,
            'upload_time_sec' => $uploadTimeSec,
        ]);
    } catch (Throwable $e) {
        if (isset($objectKey)) {
            setStatus($objectKey, 'failed', null, $e->getMessage(), 0, $sizeBytes ?? 0, 'local://upload', 0, 0);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle URL upload (queue for background processing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['file'])) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['url'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing 'url' parameter"]);
        exit;
    }

    $url = trim($input['url']);
    $filename = !empty($input['filename'])
        ? trim($input['filename'])
        : basename(parse_url($url, PHP_URL_PATH));

    $objectKey = basename($filename);

    // Create pending status
    setStatus(
        $objectKey,
        'pending',
        null,
        'Upload started',
        0,
        0,
        $url,
        0,
        0
    );

    // Spawn Worker (Background Process)
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/../upload_worker.php') . ' '
        . escapeshellarg($url) . ' '
        . escapeshellarg($objectKey) . ' > /dev/null 2>&1 &';

    exec($cmd);

    echo json_encode([
        'success' => true,
        'status' => 'pending',
        'key' => $objectKey,
        'message' => 'Upload started in background'
    ]);
    exit;
}

// Helper function for MIME types
function getMimeTypeFromFilename($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'ai', 'eps', 'ait' => 'application/postscript',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz', 'gzip' => 'application/gzip',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'tiff', 'tif' => 'image/tiff',
        'psd', 'psdt' => 'application/vnd.adobe.photoshop',
        'indt' => 'application/vnd.adobe.indesign-template',
        'mogrt' => 'application/vnd.adobe.motiongraphics-template',
        'aegraphic' => 'application/vnd.adobe.aftereffects.template',
        'prgraphic' => 'application/vnd.adobe.premiere.template',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'html', 'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        default => 'application/octet-stream',
    };
}
?>
<!doctype html>
<html lang="en" x-data="uploadApp()" x-init="init()" :data-theme="theme" class="h-full">

<head>
    <meta charset="utf-8" />
    <title>Upload — R2 Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <style>
        :root {
            color-scheme: light dark;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --border-color: #334155;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
        }

        .drop-zone {
            transition: all 0.2s ease;
        }

        .drop-zone.drag-over {
            border-color: #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
            transform: scale(1.01);
        }

        .upload-progress {
            transition: width 0.3s ease;
        }

        .file-item {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-pending {
            color: #f59e0b;
        }

        .status-uploading {
            color: #3b82f6;
        }

        .status-completed {
            color: #10b981;
        }

        .status-failed {
            color: #ef4444;
        }
    </style>
</head>

<body class="min-h-full bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100 transition-colors">

    <div class="max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="flex items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-3">
                <a href="/admin/" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Dashboard
                </a>
                <h1 class="text-2xl font-bold">📤 Upload Files</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="/admin/logout.php" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Logout</a>
                <button @click="toggleTheme()" class="px-3 py-2 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span x-text="themeLabel()"></span>
                </button>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex border-b border-slate-200 dark:border-slate-700 mb-6">
            <button @click="activeTab = 'local'"
                :class="activeTab === 'local' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2 font-medium border-b-2 -mb-px transition-colors">
                📁 Local Files
            </button>
            <button @click="activeTab = 'url'"
                :class="activeTab === 'url' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2 font-medium border-b-2 -mb-px transition-colors">
                🔗 URL Upload
            </button>
        </div>

        <!-- Local File Upload Tab -->
        <div x-show="activeTab === 'local'" x-transition>
            <!-- Drop Zone -->
            <div class="drop-zone border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg p-8 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500"
                :class="{ 'drag-over': isDragging }"
                @click="$refs.fileInput.click()"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop($event)">

                <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" multiple class="hidden" />

                <div class="text-5xl mb-4">📂</div>
                <div class="text-lg font-medium mb-2">Drop files here or click to browse</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    Supports any file type • Max size: <?= ini_get('upload_max_filesize') ?>
                </div>
            </div>

            <!-- Queue Actions -->
            <div x-show="fileQueue.length > 0" class="flex items-center justify-between mt-4 p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                <div class="text-sm">
                    <span x-text="fileQueue.length"></span> file(s) in queue
                    <span class="text-slate-500">(<span x-text="formatSize(totalQueueSize())"></span>)</span>
                </div>
                <div class="flex gap-2">
                    <button @click="clearQueue()"
                        :disabled="isUploading"
                        class="px-3 py-1.5 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-50">
                        Clear All
                    </button>
                    <button @click="startUpload()"
                        :disabled="isUploading || fileQueue.length === 0"
                        class="px-4 py-1.5 text-sm rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                        <svg x-show="isUploading" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                        </svg>
                        <span x-text="isUploading ? 'Uploading...' : 'Start Upload'"></span>
                    </button>
                </div>
            </div>

            <!-- File Queue -->
            <div x-show="fileQueue.length > 0" class="mt-4 space-y-2">
                <template x-for="(item, index) in fileQueue" :key="item.id">
                    <div class="file-item flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg">
                        <!-- File Icon -->
                        <div class="text-2xl" x-text="getFileIcon(item.file.name)"></div>

                        <!-- File Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <template x-if="!item.editing">
                                    <span class="font-medium truncate" x-text="item.customName || item.file.name"></span>
                                </template>
                                <template x-if="item.editing">
                                    <input type="text" x-model="item.customName"
                                        @blur="item.editing = false"
                                        @keyup.enter="item.editing = false"
                                        class="flex-1 px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700"
                                        x-ref="editInput" />
                                </template>
                                <button x-show="!item.editing && item.status === 'pending'"
                                    @click="item.editing = true; $nextTick(() => $refs.editInput?.focus())"
                                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                    ✏️
                                </button>
                            </div>
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <span x-text="formatSize(item.file.size)"></span>
                                <span>•</span>
                                <span x-text="item.file.type || 'Unknown type'"></span>
                            </div>

                            <!-- Progress Bar -->
                            <div x-show="item.status === 'uploading'" class="mt-2">
                                <div class="h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div class="upload-progress h-full bg-blue-500" :style="'width: ' + item.progress + '%'"></div>
                                </div>
                            </div>

                            <!-- Status Message -->
                            <div x-show="item.message" class="text-xs mt-1" :class="'status-' + item.status" x-text="item.message"></div>
                        </div>

                        <!-- Status / Actions -->
                        <div class="flex items-center gap-2">
                            <template x-if="item.status === 'pending'">
                                <button @click="removeFromQueue(index)" class="text-slate-400 hover:text-red-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </template>
                            <template x-if="item.status === 'uploading'">
                                <div class="text-blue-500">
                                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                                    </svg>
                                </div>
                            </template>
                            <template x-if="item.status === 'completed'">
                                <div class="flex items-center gap-2">
                                    <span class="text-green-500 text-xl">✓</span>
                                    <a :href="item.fileUrl" target="_blank" class="text-blue-500 hover:text-blue-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                    <button @click="copyToClipboard(item.fileUrl)" class="text-slate-400 hover:text-slate-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <template x-if="item.status === 'failed'">
                                <div class="flex items-center gap-2">
                                    <span class="text-red-500 text-xl">✗</span>
                                    <button @click="retryFile(index)" class="text-amber-500 hover:text-amber-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- URL Upload Tab -->
        <div x-show="activeTab === 'url'" x-transition>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
                <div class="space-y-4">
                    <!-- URL Input -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Source URL <span class="text-red-500">*</span></label>
                        <input type="url" x-model="urlForm.url"
                            placeholder="https://example.com/file.zip"
                            class="w-full px-4 py-2 rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <!-- Custom Filename -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Custom Filename <span class="text-slate-400">(optional)</span></label>
                        <input type="text" x-model="urlForm.filename"
                            placeholder="Leave empty to auto-detect from URL"
                            class="w-full px-4 py-2 rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center gap-3">
                        <button @click="submitUrlUpload()"
                            :disabled="urlUploading || !urlForm.url"
                            class="px-6 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                            <svg x-show="urlUploading" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                            </svg>
                            <span x-text="urlUploading ? 'Queuing...' : 'Start Upload'"></span>
                        </button>
                        <span x-show="urlResult" class="text-sm" :class="urlResult?.success ? 'text-green-500' : 'text-red-500'" x-text="urlResult?.message"></span>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-6 p-4 bg-slate-50 dark:bg-slate-900 rounded-lg text-sm">
                    <div class="font-medium mb-2">ℹ️ How URL Upload Works</div>
                    <ul class="space-y-1 text-slate-600 dark:text-slate-400">
                        <li>• The file will be downloaded and uploaded to R2 in the background</li>
                        <li>• You can monitor progress on the <a href="/admin/" class="text-blue-500 hover:underline">Dashboard</a></li>
                        <li>• Large files may take some time to process</li>
                        <li>• The filename will be extracted from the URL if not specified</li>
                    </ul>
                </div>
            </div>

            <!-- Recent URL Uploads -->
            <div x-show="urlUploads.length > 0" class="mt-6">
                <h3 class="font-medium mb-3">Recent URL Uploads</h3>
                <div class="space-y-2">
                    <template x-for="upload in urlUploads" :key="upload.key">
                        <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg">
                            <div class="text-xl">🔗</div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate" x-text="upload.key"></div>
                                <div class="text-xs text-slate-500 truncate" x-text="upload.url"></div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                                ⏳ pending
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Upload Summary -->
        <div x-show="completedCount > 0 || failedCount > 0" class="mt-6 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div x-show="completedCount > 0" class="text-green-600 dark:text-green-400">
                        ✓ <span x-text="completedCount"></span> file(s) uploaded successfully
                    </div>
                    <div x-show="failedCount > 0" class="text-red-600 dark:text-red-400">
                        ✗ <span x-text="failedCount"></span> file(s) failed
                    </div>
                </div>
                <div class="flex gap-2">
                    <button x-show="completedCount > 0" @click="copyAllLinks()"
                        class="px-3 py-1.5 text-sm rounded border border-emerald-400 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30">
                        📋 Copy All Links
                    </button>
                    <a href="/admin/" class="px-3 py-1.5 text-sm rounded border border-blue-400 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30">
                        View on Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function uploadApp() {
            return {
                theme: localStorage.getItem('r2_theme') || 'auto',
                activeTab: 'local',
                isDragging: false,
                isUploading: false,
                fileQueue: [],
                fileIdCounter: 0,
                notyf: null,

                // URL upload
                urlForm: {
                    url: '',
                    filename: ''
                },
                urlUploading: false,
                urlResult: null,
                urlUploads: [],

                init() {
                    this.applyTheme();
                    this.notyf = new Notyf({
                        duration: 4000,
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

                // File handling
                handleDrop(e) {
                    this.isDragging = false;
                    const files = Array.from(e.dataTransfer.files);
                    this.addFiles(files);
                },

                handleFileSelect(e) {
                    const files = Array.from(e.target.files);
                    this.addFiles(files);
                    e.target.value = '';
                },

                addFiles(files) {
                    files.forEach(file => {
                        this.fileQueue.push({
                            id: ++this.fileIdCounter,
                            file: file,
                            customName: file.name,
                            status: 'pending',
                            progress: 0,
                            message: '',
                            fileUrl: null,
                            editing: false
                        });
                    });
                },

                removeFromQueue(index) {
                    this.fileQueue.splice(index, 1);
                },

                clearQueue() {
                    this.fileQueue = this.fileQueue.filter(f => f.status === 'uploading');
                },

                totalQueueSize() {
                    return this.fileQueue.reduce((sum, item) => sum + item.file.size, 0);
                },

                formatSize(bytes) {
                    if (!bytes) return '0 B';
                    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    let i = 0;
                    while (bytes >= 1024 && i < units.length - 1) {
                        bytes /= 1024;
                        i++;
                    }
                    return (bytes >= 10 || i === 0 ? bytes.toFixed(0) : bytes.toFixed(1)) + ' ' + units[i];
                },

                getFileIcon(filename) {
                    const ext = filename.split('.').pop()?.toLowerCase();
                    const icons = {
                        pdf: '📄',
                        doc: '📝',
                        docx: '📝',
                        txt: '📃',
                        md: '📃',
                        xls: '📊',
                        xlsx: '📊',
                        csv: '📊',
                        ppt: '📽️',
                        pptx: '📽️',
                        jpg: '🖼️',
                        jpeg: '🖼️',
                        png: '🖼️',
                        gif: '🖼️',
                        webp: '🖼️',
                        svg: '🖼️',
                        bmp: '🖼️',
                        mp4: '🎬',
                        mov: '🎬',
                        avi: '🎬',
                        mkv: '🎬',
                        webm: '🎬',
                        mp3: '🎵',
                        wav: '🎵',
                        aac: '🎵',
                        flac: '🎵',
                        ogg: '🎵',
                        zip: '📦',
                        rar: '📦',
                        '7z': '📦',
                        tar: '📦',
                        gz: '📦',
                        html: '🌐',
                        css: '🎨',
                        js: '⚡',
                        json: '📋',
                        xml: '📋',
                        psd: '🎨',
                        ai: '🎨',
                        eps: '🎨',
                        exe: '⚙️',
                        dmg: '⚙️',
                        app: '⚙️',
                    };
                    return icons[ext] || '📄';
                },

                async startUpload() {
                    if (this.isUploading) return;
                    this.isUploading = true;

                    const pending = this.fileQueue.filter(f => f.status === 'pending');

                    for (const item of pending) {
                        await this.uploadFile(item);
                    }

                    this.isUploading = false;

                    const completed = this.fileQueue.filter(f => f.status === 'completed').length;
                    const failed = this.fileQueue.filter(f => f.status === 'failed').length;

                    if (completed > 0 && failed === 0) {
                        this.notyf.success(`All ${completed} file(s) uploaded successfully!`);
                    } else if (completed > 0 && failed > 0) {
                        this.notyf.open({
                            type: 'warning',
                            message: `${completed} uploaded, ${failed} failed`,
                            background: '#f59e0b'
                        });
                    } else if (failed > 0) {
                        this.notyf.error(`${failed} file(s) failed to upload`);
                    }
                },

                async uploadFile(item) {
                    item.status = 'uploading';
                    item.progress = 0;
                    item.message = 'Uploading...';

                    const formData = new FormData();
                    formData.append('file', item.file);
                    formData.append('filename', item.customName);

                    try {
                        const xhr = new XMLHttpRequest();

                        await new Promise((resolve, reject) => {
                            xhr.upload.addEventListener('progress', (e) => {
                                if (e.lengthComputable) {
                                    item.progress = Math.round((e.loaded / e.total) * 100);
                                    item.message = `Uploading... ${item.progress}%`;
                                }
                            });

                            xhr.addEventListener('load', () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            item.status = 'completed';
                                            item.progress = 100;
                                            item.message = 'Upload complete!';
                                            item.fileUrl = response.file_url;
                                        } else {
                                            item.status = 'failed';
                                            item.message = response.error || 'Upload failed';
                                        }
                                    } catch (e) {
                                        item.status = 'failed';
                                        item.message = 'Invalid server response';
                                    }
                                    resolve();
                                } else if (xhr.status === 401) {
                                    window.location.href = '/admin/login.php';
                                    reject(new Error('Unauthorized'));
                                } else {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        item.status = 'failed';
                                        item.message = response.error || `HTTP ${xhr.status}`;
                                    } catch (e) {
                                        item.status = 'failed';
                                        item.message = `HTTP ${xhr.status}`;
                                    }
                                    resolve();
                                }
                            });

                            xhr.addEventListener('error', () => {
                                item.status = 'failed';
                                item.message = 'Network error';
                                resolve();
                            });

                            xhr.addEventListener('abort', () => {
                                item.status = 'failed';
                                item.message = 'Upload aborted';
                                resolve();
                            });

                            xhr.open('POST', '/admin/upload.php');
                            xhr.send(formData);
                        });

                    } catch (error) {
                        item.status = 'failed';
                        item.message = error.message || 'Upload failed';
                    }
                },

                retryFile(index) {
                    const item = this.fileQueue[index];
                    item.status = 'pending';
                    item.progress = 0;
                    item.message = '';
                    item.fileUrl = null;
                },

                async copyToClipboard(text) {
                    try {
                        await navigator.clipboard.writeText(text);
                        this.notyf.success('Link copied!');
                    } catch (e) {
                        this.notyf.error('Failed to copy');
                    }
                },

                async copyAllLinks() {
                    const links = this.fileQueue
                        .filter(f => f.status === 'completed' && f.fileUrl)
                        .map(f => f.fileUrl);

                    if (links.length === 0) {
                        this.notyf.error('No completed uploads');
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(links.join('\n'));
                        this.notyf.success(`Copied ${links.length} link(s)!`);
                    } catch (e) {
                        this.notyf.error('Failed to copy');
                    }
                },

                get completedCount() {
                    return this.fileQueue.filter(f => f.status === 'completed').length;
                },

                get failedCount() {
                    return this.fileQueue.filter(f => f.status === 'failed').length;
                },

                // URL Upload
                async submitUrlUpload() {
                    if (!this.urlForm.url) return;

                    this.urlUploading = true;
                    this.urlResult = null;

                    try {
                        const res = await fetch('/admin/upload.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                url: this.urlForm.url,
                                filename: this.urlForm.filename || undefined
                            })
                        });

                        if (res.status === 401) {
                            window.location.href = '/admin/login.php';
                            return;
                        }

                        const json = await res.json();

                        if (json.success) {
                            this.urlResult = {
                                success: true,
                                message: `Queued: ${json.key}`
                            };
                            this.urlUploads.unshift({
                                key: json.key,
                                url: this.urlForm.url
                            });
                            this.notyf.success(`Upload queued: ${json.key}`);
                            this.urlForm.url = '';
                            this.urlForm.filename = '';
                        } else {
                            this.urlResult = {
                                success: false,
                                message: json.error || 'Failed to queue upload'
                            };
                            this.notyf.error(json.error || 'Failed to queue upload');
                        }
                    } catch (e) {
                        this.urlResult = {
                            success: false,
                            message: e.message || 'Request failed'
                        };
                        this.notyf.error(e.message || 'Request failed');
                    } finally {
                        this.urlUploading = false;
                    }
                }
            };
        }
    </script>
</body>

</html>