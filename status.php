<?php
// status.php — include size & timings
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/db.php';

$key = $_GET['key'] ?? '';
if ($key === '') {
    echo json_encode(['success' => false, 'error' => 'Missing key']);
    exit;
}

$pdo = db();
$st = $pdo->prepare("SELECT status, file_url, message, size_bytes, original_url, download_time_sec, upload_time_sec FROM uploads WHERE object_key = ? LIMIT 1");
$st->execute([basename($key)]);
$row = $st->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'No record found']);
    exit;
}

echo json_encode(['success' => true] + $row);
