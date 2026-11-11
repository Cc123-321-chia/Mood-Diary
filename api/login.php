<?php
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    authError(422, 'Enter a valid email address.');
}
// Login verifies the submitted password; password-composition rules apply when setting it.
if ($password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
    authError(422, 'Enter your password.');
}
require_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->prepare('SELECT user_id, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    // Use a dummy hash for unknown users to keep password verification consistent.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    // Lock only this account; shared IPs must not lock unrelated users.
    $lock = $pdo->prepare('
    SELECT COUNT(*) FROM login_attempts
    WHERE account_type = "user" AND success = 0
      AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
      AND email = ?
');
    $lock->execute([$email]);
    if ((int)$lock->fetchColumn() >= 5) {
        authError(429, 'Too many attempts. Try again in 15 minutes.');
    }

    $verified = password_verify($password, $hash);

    $record = $pdo->prepare('
    INSERT INTO login_attempts(account_type, email, ip_address, user_agent, success)
    VALUES("user", ?, ?, ?, ?)
');
    $record->execute([$email, $ip, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), ($user && $verified && (int)$user['is_active']) ? 1 : 0]);

    if (!$user || !$verified || !(int) $user['is_active']) {
        writeSecurityLog($pdo, 'LOGIN_FAILED', 'Failed login attempt for ' . $email . '.', $user ? (int)$user['user_id'] : null);
        authError(401, 'Email or password is incorrect.');
    }
    startUserSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['user_id'], 'expires_at' => time() + 28800, 'credential_version' => hash('sha256', $user['password_hash'])];
    writeSecurityLog($pdo, 'LOGIN_SUCCESS', 'User signed in successfully.', (int)$user['user_id']);
    $pdo->prepare('DELETE FROM login_attempts WHERE account_type = "user" AND email = ? AND success = 0')->execute([$email]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Mood Diary login failed: ' . $e->getMessage());
    writeSecurityLog($pdo, 'LOGIN_ERROR', 'A login request could not be completed.');
    authError(500, 'Unable to log in. Please try again later.');
}
