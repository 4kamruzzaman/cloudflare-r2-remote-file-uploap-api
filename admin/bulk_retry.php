<?php
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

try {
    $pdo = db();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['keys']) || !is_array($input['keys'])) {
        echo json_encode(['success' => false, 'error' => 'Missing keys array']);
        exit;
    }
    $keys = array_values(array_unique(array_filter($input['keys'], 'strlen')));
    if (!$keys) {
        echo json_encode(['success' => false, 'error' => 'No valid keys']);
        exit;
    }

    // fetch records
    $in = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT object_key, original_url FROM uploads WHERE object_key IN ($in)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) $map[$r['object_key']] = $r;

    $results = [];
    foreach ($keys as $key) {
        $row = $map[$key] ?? null;
        if (!$row) {
            $results[] = ['key' => $key, 'status' => 'skipped', 'reason' => 'not found'];
            continue;
        }
        if (empty($row['original_url'])) {
            $results[] = ['key' => $key, 'status' => 'skipped', 'reason' => 'original_url missing'];
            continue;
        }

        // Mark pending + bump retries
        $pdo->prepare("UPDATE uploads SET status='pending', message='Retry queued', retries=COALESCE(retries,0)+1, updated_at=NOW() WHERE object_key=?")
            ->execute([$key]);

        // background run
        $cmd = 'php ' . escapeshellarg('../upload_worker.php') . ' ' .
            escapeshellarg($row['original_url']) . ' ' .
            escapeshellarg($key) . ' > /dev/null 2>&1 &';
        exec($cmd);

        $results[] = ['key' => $key, 'status' => 'queued'];
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
