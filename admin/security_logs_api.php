<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
$query = mb_substr($query, 0, 200);
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;
$where = '';
$params = [];
if ($query !== '') {
    $where = "WHERE (sl.event_type LIKE :event_type OR sl.description LIKE :description
        OR sl.ip_address LIKE :ip_address OR u.name LIKE :user_name OR u.email LIKE :user_email
        OR a.name LIKE :admin_name OR a.email LIKE :admin_email)";
    $like = '%' . $query . '%';
    $params = [
        'event_type' => $like,
        'description' => $like,
        'ip_address' => $like,
        'user_name' => $like,
        'user_email' => $like,
        'admin_name' => $like,
        'admin_email' => $like,
    ];
}

try {
    $count = $pdo->prepare("SELECT COUNT(*) FROM security_logs sl
        LEFT JOIN users u ON u.user_id = sl.user_id
        LEFT JOIN admins a ON a.admin_id = sl.admin_id $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT sl.log_id, sl.event_type, sl.description, sl.ip_address,
            sl.created_at, sl.user_id, sl.admin_id,
            u.name AS user_name, u.email AS user_email,
            a.name AS admin_name, a.email AS admin_email
        FROM security_logs sl
        LEFT JOIN users u ON u.user_id = sl.user_id
        LEFT JOIN admins a ON a.admin_id = sl.admin_id
        $where
        ORDER BY sl.created_at DESC, sl.log_id DESC
        LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    echo json_encode([
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Admin security logs could not be loaded: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load security logs.']);
}
