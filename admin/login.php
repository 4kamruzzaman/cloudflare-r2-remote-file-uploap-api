<?php
// admin/login.php
if (session_status() !== PHP_SESSION_ACTIVE) {
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

$env = @parse_ini_file(__DIR__ . '/../.env') ?: [];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');

    $ADMIN_USER = $env['ADMIN_USER'] ?? 'admin';
    $ADMIN_PASS_HASH = $env['ADMIN_PASS_HASH'] ?? null;

    if (!$ADMIN_PASS_HASH) {
        $error = 'Admin password not configured. Set ADMIN_PASS_HASH in .env';
    } elseif ($u !== $ADMIN_USER) {
        $error = 'Invalid credentials';
    } elseif (!password_verify($p, $ADMIN_PASS_HASH)) {
        $error = 'Invalid credentials';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $u;
        header('Location: /admin');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-slate-100">
    <div class="w-full max-w-sm bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-semibold mb-4 text-center">🔐 Admin Login</h1>
        <?php if ($error): ?>
            <div class="mb-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <label class="block text-sm mb-1">Username</label>
            <input type="text" name="username" class="w-full border rounded px-3 py-2 mb-3" required>

            <label class="block text-sm mb-1">Password</label>
            <input type="password" name="password" class="w-full border rounded px-3 py-2 mb-4" required>

            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded px-3 py-2">Login</button>
        </form>
    </div>
</body>

</html>