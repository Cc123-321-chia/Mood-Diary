<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
requireJsonPost();
startAdminSession();
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => 'Strict',
]);
session_destroy();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok' => true]);
