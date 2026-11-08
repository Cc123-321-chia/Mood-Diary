<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
startAdminSession();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok' => true, 'csrf_token' => $_SESSION['csrf_token']]);
