<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('MOOD_ADMIN_SID');
    ini_set('session.use_strict_mode', '1');
    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $script = str_replace('\\', '/', is_string($requestPath) && $requestPath !== '' ? $requestPath : (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminPosition = strpos($script, '/admin/');
    $cookiePath = $adminPosition === false ? '/' : substr($script, 0, $adminPosition + 7);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
        'path' => $cookiePath,
    ]);
    session_start();
    header('Cache-Control: no-store');
}

function requireAdmin(bool $json = false): array
{
    startAdminSession();
    $admin = false;
    if (!empty($_SESSION['admin_id']) && ($_SESSION['expires_at'] ?? 0) >= time()) {
        global $pdo;
        require_once __DIR__ . '/../config/db.php';
        $stmt = $pdo->prepare('SELECT admin_id, name, email, password_hash, must_change_password FROM admins WHERE admin_id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
    }
    if (!$admin || !hash_equals(hash('sha256', $admin['password_hash']), (string)($_SESSION['credential_version'] ?? ''))) {
        $_SESSION = [];
        if ($json) authError(401, 'Please log in as an administrator.');
        header('Location: login.html');
        exit;
    }
    $_SESSION['must_change_password'] = (bool)$admin['must_change_password'];
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if ($_SESSION['must_change_password']) {
        $allowedDuringPasswordChange = ['change_password.php', 'logout.php'];
        $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!in_array($scriptName, $allowedDuringPasswordChange, true)) {
            if ($json) authError(403, 'Change your temporary password before continuing.');
            header('Location: change_password.php');
            exit;
        }
    }
    unset($admin['password_hash']);
    return $admin;
}
