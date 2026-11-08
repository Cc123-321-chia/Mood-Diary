<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin(true);
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode(['success'=>false,'message'=>'This endpoint is retired. Use api/users.php.']);
