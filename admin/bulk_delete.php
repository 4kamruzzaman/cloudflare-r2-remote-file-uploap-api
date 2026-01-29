<?php
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require '../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$input = json_decode(file_get_contents('php://input'), true);
$keys  = $input['keys'] ?? [];

if (empty($keys) || !is_array($keys)) {
    echo json_encode(['success' => false, 'error' => 'No keys received']);
    exit;
}

$env = parse_ini_file('../.env');
$db  = db();

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $env['R2_REGION'] ?? 'auto',
    'endpoint' => "https://{$env['R2_ACCOUNT_ID']}.r2.cloudflarestorage.com",
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => $env['R2_KEY_ID'],
        'secret' => $env['R2_SECRET_KEY']
    ]
]);

$deletedDb  = 0;
$deletedR2  = 0;
$errors     = [];

// ---------------- Delete Loop ----------------
foreach ($keys as $key) {

    // 1️⃣ Delete from DB
    $stmt = $db->prepare("DELETE FROM uploads WHERE object_key = ?");
    $stmt->execute([$key]);
    if ($stmt->rowCount() > 0) {
        $deletedDb++;
    }

    // 2️⃣ Delete from R2
    try {
        $s3->deleteObject([
            'Bucket' => $env['R2_BUCKET'],
            'Key'    => $key
        ]);
        $deletedR2++;
    } catch (AwsException $e) {
        $errors[] = [
            'key'   => $key,
            'error' => $e->getAwsErrorMessage()
        ];
    }
}

echo json_encode([
    'success'     => true,
    'deleted_db'  => $deletedDb,
    'deleted_r2'  => $deletedR2,
    'errors'      => $errors
]);
