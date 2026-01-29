<?php
// auth_guard.php — session login guard for admin + API endpoints

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Hardened session cookie params
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['admin_logged_in'])) {
    // If this is an API endpoint, return JSON 401
    $wantsJson = false;
    if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        $wantsJson = true;
    }
    // Fallback: treat non-HTML endpoints as JSON
    $ext = strtolower(pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'json', ''], true)) {
        $wantsJson = true;
    }

    if (!isset($admin) && $wantsJson) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    } else {
        header('Location: /admin/login.php');
    }
    exit;
}
