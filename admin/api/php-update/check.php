<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$admin = phpUpdateAdmin();
$input = requireJsonPost();
phpUpdateRequireCsrf($input);
require_once __DIR__ . '/../../../config/db.php';
try {
    if (!phpUpdateSchemaReady($pdo)) phpUpdateReply(['ok' => false, 'error' => 'The PHP monitoring table is missing. Run database/php_update_management.sql in the mood_diary database.'], 503);
    $state = phpUpdateLoadState($pdo, true);
    $log = $pdo->prepare("INSERT INTO security_logs(admin_id,event_type,description,ip_address,user_agent) VALUES(?,'PHP_RELEASE_CHECK','Administrator checked official PHP release information.',?,?)");
    $log->execute([(int)$admin['admin_id'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null]);
    phpUpdateReply(['ok' => true, 'csrf_token' => (string)($_SESSION['csrf_token'] ?? '')] + $state);
} catch (Throwable $e) {
    error_log('Mood Diary PHP release check failed: ' . $e->getMessage());
    $message = !extension_loaded('curl') ? 'The PHP cURL extension is required for secure release checks.' : 'The official PHP release check failed. Check outbound HTTPS access and try again.';
    phpUpdateReply(['ok' => false, 'error' => $message], 503);
}
