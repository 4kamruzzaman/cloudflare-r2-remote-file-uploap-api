<?php

/**
 * upload_worker.php — flat filenames; stores size_bytes + timings
 * Usage: php upload_worker.php <url> <objectKey>
 */
date_default_timezone_set('Asia/Dhaka');
set_time_limit(0);
ini_set('memory_limit', '1024M');

require 'vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\AwsException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// args
if ($argc < 3) {
    exit("Usage: php upload_worker.php <url> <objectKey>\n");
}
$url       = $argv[1];
$objectKey = basename($argv[2]);

// env + clients
$e   = env();
$s3  = r2();
$http = new Client([
    'timeout'         => 0,
    'connect_timeout' => (int)($e['DL_CONNECT_TIMEOUT'] ?? 30),
    'verify'          => false,
    'headers'         => ['User-Agent' => 'R2-Uploader/1.0']
]);

// tuning
$PART_MB        = max(5, (int)($e['R2_PART_SIZE_MB'] ?? 32));
$PART_SIZE      = $PART_MB * 1024 * 1024;
$DOWNLOAD_RETRY = 3;
$UPLOAD_RETRY   = 3;

// mime
function getMimeType($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'ai', 'eps', 'ait'           => 'application/postscript',
        'pdf'                      => 'application/pdf',
        'zip'                      => 'application/zip',
        'jpg', 'jpeg'               => 'image/jpeg',
        'png'                      => 'image/png',
        'gif'                      => 'image/gif',
        'svg'                      => 'image/svg+xml',
        'psdt'                     => 'application/vnd.adobe.photoshop',
        'indt'                     => 'application/vnd.adobe.indesign-template',
        'mogrt'                    => 'application/vnd.adobe.motiongraphics-template',
        'aegraphic'                => 'application/vnd.adobe.aftereffects.template',
        'prgraphic'                => 'application/vnd.adobe.premiere.template',
        'mp4'                      => 'video/mp4',
        'mov'                      => 'video/quicktime',
        'wav'                      => 'audio/wav',
        'aac'                      => 'audio/aac',
        default                    => 'application/octet-stream',
    };
}
$mimeType = getMimeType($objectKey);

// helpers
function getRemoteSizeOrNull(Client $http, string $url): ?int
{
    try {
        $r = $http->head($url);
        $len = $r->getHeaderLine('Content-Length');
        if ($len !== '' && ctype_digit($len)) return (int)$len;
    } catch (GuzzleException $e) {
    }
    return null;
}
function downloadToTmp(Client $http, string $url, int $retries): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'r2_');
    if ($tmp === false) throw new RuntimeException('Cannot create temp file');

    for ($i = 1; $i <= $retries; $i++) {
        @unlink($tmp);
        $tmp = tempnam(sys_get_temp_dir(), 'r2_');

        $t0 = microtime(true);
        try {
            $http->request('GET', $url, ['sink' => $tmp]);
        } catch (GuzzleException $e) {
            // try again
        }
        $elapsed = (int)round(microtime(true) - $t0);

        clearstatcache(true, $tmp);
        $local = @filesize($tmp);
        if ($local && $local > 0) {
            return [$tmp, (int)$local, $elapsed];
        }
        sleep(min(10, $i * 2));
    }
    @unlink($tmp);
    throw new RuntimeException('Download failed after retries');
}
function uploadFileToR2(S3Client $s3, array $e, string $path, string $key, string $mime, int $partSize): int
{
    // overwrite behavior
    try {
        $s3->deleteObject(['Bucket' => $e['R2_BUCKET'], 'Key' => $key]);
    } catch (Throwable $t) {
    }

    $t0 = microtime(true);
    $uploader = new MultipartUploader($s3, $path, [
        'bucket'      => $e['R2_BUCKET'],
        'key'         => $key,
        'part_size'   => $partSize,
        'concurrency' => 4,
        'before_initiate' => function ($params) use ($mime) {
            $params['ContentType']        = $mime;
            $params['ContentDisposition'] = 'attachment';
            $params['ACL']                = 'public-read';
            return $params;
        }
    ]);
    $uploader->upload();
    return (int)round(microtime(true) - $t0);
}
function verifyUploadSize(S3Client $s3, array $e, string $key, int $localBytes): bool
{
    try {
        $head = $s3->headObject(['Bucket' => $e['R2_BUCKET'], 'Key' => $key]);
        $remote = (int)($head['ContentLength'] ?? 0);
        return $remote === $localBytes;
    } catch (AwsException $ex) {
        return false;
    }
}

// workflow
try {
    // prepare
    setStatus($objectKey, 'pending', null, 'Preparing download', 0);

    // download
    [$tmpFile, $sizeBytes, $dlSec] = downloadToTmp($http, $url, $DOWNLOAD_RETRY);

    // save size/time immediately
    setStatus($objectKey, 'pending', null, 'Uploading to R2', 0, $sizeBytes, null, $dlSec, 0);

    // upload with retries + verify
    $attempt = 0;
    $ulSec   = 0;
    while (true) {
        $attempt++;
        try {
            $ulSec = uploadFileToR2($s3, $e, $tmpFile, $objectKey, $mimeType, $PART_SIZE);

            if (verifyUploadSize($s3, $e, $objectKey, $sizeBytes)) {
                $urlPublic = r2_url($objectKey);
                setStatus($objectKey, 'completed', $urlPublic, 'Uploaded successfully', 0, $sizeBytes, null, $dlSec, $ulSec);
                echo json_encode(['success' => true, 'file_url' => $urlPublic, 'size_bytes' => $sizeBytes, 'download_time_sec' => $dlSec, 'upload_time_sec' => $ulSec]);
                break;
            }

            // corruption → retry
            try {
                r2_delete($objectKey);
            } catch (Throwable $t) {
            }
            if ($attempt >= $UPLOAD_RETRY) {
                setStatus($objectKey, 'failed', null, 'Upload corrupted after retries', 0, $sizeBytes, null, $dlSec, $ulSec);
                throw new RuntimeException('Upload corrupted after retries');
            }
            setStatus($objectKey, 'pending', null, "Corruption detected. Retrying upload ($attempt/$UPLOAD_RETRY)…", 0, $sizeBytes, null, $dlSec, $ulSec);
            sleep(min(10, $attempt * 2));
        } catch (AwsException $eAws) {
            if ($attempt >= $UPLOAD_RETRY) {
                setStatus($objectKey, 'failed', null, 'Upload failed after retries: ' . $eAws->getMessage(), 0, $sizeBytes, null, $dlSec, $ulSec);
                throw new RuntimeException('Upload failed after retries: ' . $eAws->getMessage());
            }
            setStatus($objectKey, 'pending', null, "R2 error: " . $eAws->getMessage() . " — retrying ($attempt/$UPLOAD_RETRY)…", 0, $sizeBytes, null, $dlSec, $ulSec);
            sleep(min(10, $attempt * 2));
        }
    }
} catch (Throwable $ex) {
    setStatus($objectKey, 'failed', null, $ex->getMessage(), 0);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
} finally {
    if (!empty($tmpFile) && is_file($tmpFile)) @unlink($tmpFile);
}
