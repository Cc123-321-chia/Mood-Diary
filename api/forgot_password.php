<?php
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    authError(422, 'Enter a valid email address.');
}
require_once __DIR__ . '/../config/mailer.php';
try { moodMailer(); } catch (Throwable $e) {
    authError(503, 'Password reset emails are not configured. Please contact the site owner.');
}
require_once __DIR__ . '/../config/db.php';
$userId = null;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND is_active = 1 FOR UPDATE');
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        $stmt = $pdo->prepare('SELECT 1 FROM password_resets WHERE user_id = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            $code = (string) random_int(100000, 999999);
            $hash = hash('sha256', $userId . ':' . $code);
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, requested_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
                ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), requested_at = NOW(), attempts = 0')
                ->execute([$userId, $hash]);
            sendMoodResetCode($email, $code);
        }
    }
    $pdo->commit();
    writeSecurityLog($pdo, 'PASSWORD_RESET_REQUESTED', 'Password reset requested.', $userId ? (int)$userId : null);
    echo json_encode(['ok' => true, 'message' => 'If this email belongs to an active account, a verification code will be sent. Check your inbox and spam folder. Please wait 1 minute before requesting another code.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mood Diary reset request failed.');
    writeSecurityLog($pdo, 'PASSWORD_RESET_REQUEST_FAILED', 'A password reset request could not be completed.', $userId ? (int)$userId : null);
    authError(500, 'Unable to request a reset. Please try again later.');
}
