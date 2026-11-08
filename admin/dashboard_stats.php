<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $newUsers = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY')->fetchColumn();
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $diariesToday = (int)$pdo->query('SELECT COUNT(*) FROM diaries WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY')->fetchColumn();
    $logsToday = (int)$pdo->query('SELECT COUNT(*) FROM security_logs WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY')->fetchColumn();

    $resolutionReady = $pdo->query("SHOW TABLES LIKE 'security_log_resolutions'")->fetchColumn() !== false;
    $resolutionJoin = $resolutionReady ? 'LEFT JOIN security_log_resolutions sr ON sr.log_id=sl.log_id' : '';
    $resolutionFilter = $resolutionReady ? ' AND sr.log_id IS NULL' : '';
    $criticalCount = (int)$pdo->query("SELECT COUNT(*) FROM security_logs sl $resolutionJoin
        WHERE sl.created_at >= CURDATE() AND sl.created_at < CURDATE() + INTERVAL 1 DAY
          $resolutionFilter
          AND LOWER(CONCAT(COALESCE(sl.event_type,''),' ',COALESCE(sl.description,'')))
              REGEXP 'fail|blocked|unauthori[sz]ed|denied|invalid|suspicious|attack'")->fetchColumn();

    $criticalSql = "SELECT sl.log_id,sl.event_type,sl.description,sl.ip_address,sl.created_at,
                           COALESCE(u.name,a.name,'System') AS account_name,
                           COALESCE(u.email,a.email,'') AS account_email
                    FROM security_logs sl
                    LEFT JOIN users u ON u.user_id=sl.user_id
                    LEFT JOIN admins a ON a.admin_id=sl.admin_id
                    $resolutionJoin
                    WHERE sl.created_at >= CURDATE() AND sl.created_at < CURDATE() + INTERVAL 1 DAY
                      $resolutionFilter
                      AND LOWER(CONCAT(COALESCE(sl.event_type,''),' ',COALESCE(sl.description,'')))
                          REGEXP 'fail|blocked|unauthori[sz]ed|denied|invalid|suspicious|attack'
                    ORDER BY sl.created_at DESC,sl.log_id DESC LIMIT 8";
    $criticalLogs = $pdo->query($criticalSql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'new_users_today' => $newUsers,
            'total_users' => $totalUsers,
            'diaries_today' => $diariesToday,
            'security_logs_today' => $logsToday,
        ],
        'critical_count' => $criticalCount,
        'critical_logs' => $criticalLogs,
        'resolution_ready' => $resolutionReady,
        'date' => date('Y-m-d'),
        'csrf_token' => $_SESSION['csrf_token'],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Dashboard statistics could not be loaded.']);
}
