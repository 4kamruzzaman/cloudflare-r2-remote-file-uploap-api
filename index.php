<?php
// index.php — start upload (flat keys; overwrite behavior handled in worker)
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['url'])) {
    echo json_encode(['success' => false, 'error' => "Missing 'url' parameter"]);
    exit;
}

$url = trim($input['url']);
$filename = !empty($input['filename'])
    ? trim($input['filename'])
    : basename(parse_url($url, PHP_URL_PATH));

// enforce flat key
$objectKey = basename($filename);

// create/mark pending immediately with original_url + zero metrics
setStatus(
    $objectKey,
    'pending',
    null,                       // file_url
    'Upload started',           // message
    0,                          // retries
    0,                          // size_bytes
    $url,                       // original_url
    0,                          // download_time_sec
    0                           // upload_time_sec
);

// start worker
$cmd = 'php ' . escapeshellarg(__DIR__ . '/upload_worker.php') . ' '
    . escapeshellarg($url) . ' '
    . escapeshellarg($objectKey) . ' > /dev/null 2>&1 &';
exec($cmd);

echo json_encode([
    'success' => true,
    'status'  => 'pending',
    'key'     => $objectKey,
    'message' => 'Upload started'
]);
