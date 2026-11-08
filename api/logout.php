<?php
require_once __DIR__ . '/../config/auth.php';
requireJsonPost();
startUserSession();
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
require_once __DIR__ . '/../config/db.php';
if ($userId) writeSecurityLog($pdo, 'LOGOUT', 'User signed out.', $userId);
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $params['path'],
    'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict',
]);
session_destroy();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
