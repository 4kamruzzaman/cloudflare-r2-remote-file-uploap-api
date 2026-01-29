<?php
// db.php — shared helpers (PDO + R2 + status helpers)
date_default_timezone_set('Asia/Dhaka');

function env(): array
{
    static $env;
    if ($env) return $env;
    $env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_TYPED) ?: [];
    return $env;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $e = env();
    $dsn = "mysql:host={$e['DB_HOST']};dbname={$e['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $e['DB_USER'], $e['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // long-running uploads
    $pdo->query("SET SESSION wait_timeout = 1990");
    $pdo->query("SET SESSION interactive_timeout = 1990");

    return $pdo;
}

/**
 * Upsert status row.
 * Defaults (per your choice): size_bytes=0, download_time_sec=0, upload_time_sec=0
 */
function setStatus(
    string $objectKey,
    string $status,
    ?string $fileUrl = null,
    ?string $message = null,
    int $retries = 0,
    int $sizeBytes = 0,
    ?string $originalUrl = null,
    int $downloadTimeSec = 0,
    int $uploadTimeSec = 0
): void {
    $pdo = db();

    // Ensure a UNIQUE index on uploads.object_key for ON DUPLICATE KEY to work best
    // ALTER TABLE uploads ADD UNIQUE KEY uk_object_key (object_key);

    $sql = "
        INSERT INTO uploads
            (object_key, status, file_url, message, retries, size_bytes, original_url, download_time_sec, upload_time_sec, created_at, updated_at)
        VALUES
            (:object_key, :status, :file_url, :message, :retries, :size_bytes, :original_url, :download_time_sec, :upload_time_sec, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            file_url = VALUES(file_url),
            message = VALUES(message),
            retries = VALUES(retries),
            size_bytes = VALUES(size_bytes),
            original_url = COALESCE(VALUES(original_url), original_url),
            download_time_sec = VALUES(download_time_sec),
            upload_time_sec = VALUES(upload_time_sec),
            updated_at = NOW()
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':object_key'        => $objectKey,
        ':status'            => $status,
        ':file_url'          => $fileUrl,
        ':message'           => $message,
        ':retries'           => $retries,
        ':size_bytes'        => $sizeBytes,
        ':original_url'      => $originalUrl,
        ':download_time_sec' => $downloadTimeSec,
        ':upload_time_sec'   => $uploadTimeSec,
    ]);
}

/** AWS S3 client for Cloudflare R2 */
function r2(): Aws\S3\S3Client
{
    static $s3;
    if ($s3) return $s3;

    $e = env();
    $s3 = new Aws\S3\S3Client([
        'version'                 => 'latest',
        'region'                  => $e['R2_REGION'] ?? 'auto',
        'endpoint'                => "https://{$e['R2_ACCOUNT_ID']}.r2.cloudflarestorage.com",
        'use_path_style_endpoint' => true,
        'credentials'             => [
            'key'    => $e['R2_KEY_ID'],
            'secret' => $e['R2_SECRET_KEY'],
        ],
    ]);
    return $s3;
}

/** Build public file URL from key (filename) */
function r2_url(string $key): string
{
    $base = rtrim(env()['R2_CUSTOM_DOMAIN'] ?? '', '/');
    return $base . '/' . ltrim($key, '/');
}

/** Delete an object from R2 (ignore if missing). */
function r2_delete(string $key): void
{
    $e  = env();
    $s3 = r2();
    try {
        $s3->deleteObject([
            'Bucket' => $e['R2_BUCKET'],
            'Key'    => ltrim($key, '/'),
        ]);
    } catch (Throwable $t) {
        // ignore
    }
}
