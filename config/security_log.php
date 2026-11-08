<?php
declare(strict_types=1);

/**
 * Add an audit event without allowing a logging failure to break the user action.
 * user_id/admin_id identify the related account when that account still exists.
 */
function writeSecurityLog(
    PDO $pdo,
    string $eventType,
    string $description,
    ?int $userId = null,
    ?int $adminId = null
): bool {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO security_logs (admin_id, user_id, event_type, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $userId,
            substr($eventType, 0, 50),
            substr($description, 0, 4000),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('Mood Diary security log write failed: ' . $e->getMessage());
        return false;
    }
}
