<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
startAdminSession();
$csrf = is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : '';
if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) authError(403, 'Refresh the page and try again.');
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) authError(422, 'Enter a valid email address.');
if ($password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) authError(422, 'Enter your password.');
require_once __DIR__ . '/../../config/db.php';
try {
    $stmt = $pdo->prepare('SELECT admin_id, password_hash, must_change_password FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    $hash = $admin['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    // Lock only this account; shared IPs must not lock unrelated users.
    $lock = $pdo->prepare('
        SELECT COUNT(*) FROM login_attempts
        WHERE account_type = "admin" AND success = 0
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
        VALUES("admin", ?, ?, ?, ?)
    ');
    $record->execute([
        $email,
        $ip,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ($admin && $verified) ? 1 : 0,
    ]);
    if (!$admin || !$verified) {
        $log = $pdo->prepare("INSERT INTO security_logs(event_type,description,ip_address,user_agent) VALUES('ADMIN_LOGIN_FAILED',?,?,?)");
        $log->execute(['Failed admin login attempt for ' . $email, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null]);
        authError(401, 'Admin email or password is incorrect.');
    }
    session_regenerate_id(true);
    $_SESSION = [
        'admin_id' => (int)$admin['admin_id'],
        'credential_version' => hash('sha256', $hash),
        'expires_at' => time() + 28800,
        'csrf_token' => bin2hex(random_bytes(32)),
        'must_change_password' => (bool)$admin['must_change_password'],
    ];
    $log = $pdo->prepare("INSERT INTO security_logs(admin_id,event_type,description,ip_address,user_agent) VALUES(?,'ADMIN_LOGIN_SUCCESS',?,?,?)");
    $log->execute([(int)$admin['admin_id'], 'Administrator signed in successfully.', substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null]);
    $pdo->prepare('DELETE FROM login_attempts WHERE account_type = "admin" AND email = ? AND success = 0')->execute([$email]);
    echo json_encode([
        'ok' => true,
        'must_change_password' => (bool)$admin['must_change_password'],
    ]);
} catch (Throwable $e) {
    error_log('Mood Diary admin login failed.');
    authError(500, 'Unable to log in. Please try again later.');
}
