<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
phpUpdateAdmin();
require_once __DIR__ . '/../../../config/db.php';
try {
    if (!phpUpdateSchemaReady($pdo)) phpUpdateReply(['ok' => false, 'csrf_token' => (string)($_SESSION['csrf_token'] ?? ''), 'error' => 'The PHP monitoring table is missing. Run database/php_update_management.sql in the mood_diary database.'], 503);
    $state = phpUpdateLoadState($pdo);
    $state['status']['environment'] = phpUpdateEnvironment();
    phpUpdateReply(['ok' => true, 'csrf_token' => (string)($_SESSION['csrf_token'] ?? '')] + $state);
} catch (Throwable $e) {
    error_log('Mood Diary PHP update status unavailable: ' . $e->getMessage());
    $message = !extension_loaded('curl') ? 'The PHP cURL extension is required for secure release checks.' : 'The official PHP release check failed. Check outbound HTTPS access and try again.';
    phpUpdateReply(['ok' => false, 'csrf_token' => (string)($_SESSION['csrf_token'] ?? ''), 'error' => $message], 503);
}
