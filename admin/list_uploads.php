<?php
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$pdo = db();

/**
 * Query params
 */
$page   = isset($_GET['page'])  && ctype_digit((string)$_GET['page'])  ? (int)$_GET['page']  : 1;
$limit  = isset($_GET['limit']) && ctype_digit((string)$_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit  = max(1, min($limit, 200)); // cap to 200
$offset = ($page - 1) * $limit;

$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = (isset($_GET['status']) && $_GET['status'] !== '') ? trim((string)$_GET['status']) : '';

/**
 * Sorting: expect sort[field]=dir (e.g. sort[id]=desc)
 */
$allowedSort = [
    'id' => 'id',
    'object_key' => 'object_key',
    'status' => 'status',
    'size_bytes' => 'size_bytes',
    'retries' => 'retries',
    'created_at' => 'created_at',
    'updated_at' => 'updated_at',
    'download_time_sec' => 'download_time_sec',
    'upload_time_sec' => 'upload_time_sec',
];

$sortField = 'id';
$sortDir   = 'DESC';

if (isset($_GET['sort']) && is_array($_GET['sort']) && count($_GET['sort']) > 0) {
    // take the first provided sort
    foreach ($_GET['sort'] as $field => $dir) {
        if (isset($allowedSort[$field])) {
            $sortField = $allowedSort[$field];
            $sortDir   = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';
            break;
        }
    }
}

/**
 * WHERE builder
 * Search A: across id, object_key (covers filename), original_url, message
 */
$where = [];
$params = [];

if ($status !== '') {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($q !== '') {
    // If q is numeric, allow direct id match too
    if (ctype_digit($q)) {
        $where[] = "(id = ? OR object_key LIKE ? OR original_url LIKE ? OR message LIKE ?)";
        $params[] = (int)$q;
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    } else {
        $where[] = "(object_key LIKE ? OR original_url LIKE ? OR message LIKE ?)";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/**
 * COUNT total
 */
$sqlCount = "SELECT COUNT(*) AS c FROM uploads {$whereSql}";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

/**
 * ORDER BY — numeric sort for numeric columns
 */
$numericCols = ['id', 'size_bytes', 'retries', 'download_time_sec', 'upload_time_sec'];
$orderExpr = in_array($sortField, $numericCols, true)
    ? "COALESCE($sortField,0) $sortDir"
    : "$sortField $sortDir";

/**
 * DATA query
 */
$sql = "
    SELECT
        id,
        object_key,
        status,
        file_url,
        message,
        retries,
        size_bytes,
        original_url,
        download_time_sec,
        upload_time_sec,
        created_at,
        updated_at
    FROM uploads
    {$whereSql}
    ORDER BY {$orderExpr}
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Response
 */
$lastPage = max(1, (int)ceil($total / $limit));

echo json_encode([
    'data'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'last_page' => $lastPage,
]);
