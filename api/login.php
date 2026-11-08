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
    $verified = password_verify($password, $hash);
    if (!$user || !$verified || !(int) $user['is_active']) {
        writeSecurityLog($pdo, 'LOGIN_FAILED', 'Failed login attempt for ' . $email . '.', $user ? (int)$user['user_id'] : null);
        authError(401, 'Email or password is incorrect.');
    }
    startUserSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['user_id'], 'expires_at' => time() + 28800, 'credential_version' => hash('sha256', $user['password_hash'])];
    writeSecurityLog($pdo, 'LOGIN_SUCCESS', 'User signed in successfully.', (int)$user['user_id']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Mood Diary login failed: ' . $e->getMessage());
    writeSecurityLog($pdo, 'LOGIN_ERROR', 'A login request could not be completed.');
    authError(500, 'Unable to log in. Please try again later.');
}
