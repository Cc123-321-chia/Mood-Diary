<?php
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$confirm = is_string($input['confirmPassword'] ?? null) ? $input['confirmPassword'] : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) authError(422, 'Enter your registered email address.');
if (!preg_match('/\A[0-9]{6}\z/', $code)) authError(422, 'Enter the 6-digit verification code from your email.');
if (!validPassword($password) || strlen($password) > 72 || strpos($password, "\0") !== false) {
    authError(422, 'Use at least 8 characters, 1 special character and 1 uppercase letter, with at most 72 UTF-8 bytes.');
}
if ($password !== $confirm) authError(422, 'Passwords do not match.');
require_once __DIR__ . '/../config/db.php';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT r.user_id, r.token_hash, r.attempts FROM password_resets r JOIN users u ON u.user_id = r.user_id
        WHERE u.email = ? AND r.expires_at > NOW() AND u.is_active = 1 FOR UPDATE');
    $stmt->execute([$email]);
    $reset = $stmt->fetch();
    if (!$reset || $reset['attempts'] >= 5) {
        $pdo->rollBack();
        writeSecurityLog($pdo, 'PASSWORD_RESET_FAILED', 'Password reset code was invalid, expired, or locked.');
        authError(422, 'This code is invalid, expired or locked. Please request a new code.');
    }
    $userId = $reset['user_id'];
    if (!hash_equals($reset['token_hash'], hash('sha256', $userId . ':' . $code))) {
        $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE user_id = ?')->execute([$userId]);
        $pdo->commit();
        writeSecurityLog($pdo, 'PASSWORD_RESET_FAILED', 'Incorrect password reset code submitted.', (int)$userId);
        authError(422, 'This code is invalid, expired or locked. Please request a new code.');
    }
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    $pdo->commit();
    writeSecurityLog($pdo, 'PASSWORD_RESET_SUCCESS', 'Password reset completed successfully.', (int)$userId);
    try {
        require_once __DIR__ . '/../config/mailer.php';
        sendMoodPasswordChanged($email);
    } catch (Throwable $mailError) {
        error_log('Mood Diary password-change notification could not be sent.');
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mood Diary password reset failed.');
    writeSecurityLog($pdo, 'PASSWORD_RESET_ERROR', 'A password reset request could not be completed.');
    authError(500, 'Unable to reset your password. Please try again later.');
}
