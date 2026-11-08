<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function password_change_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$input = requireJsonPost();
$csrf = is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : '';
if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
    password_change_reply(['ok' => false, 'error' => 'Refresh the page and try again.'], 403);
}
if (empty($_SESSION['must_change_password'])) {
    password_change_reply(['ok' => false, 'error' => 'A password change is not required.'], 409);
}

$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$confirmation = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
if (!validPassword($password) || strlen($password) > 72 || strpos($password, "\0") !== false) {
    password_change_reply(['ok' => false, 'error' => 'Use at least 8 characters, 1 special character and 1 uppercase letter (A–Z), with at most 72 UTF-8 bytes.'], 422);
}
if ($password !== $confirmation) {
    password_change_reply(['ok' => false, 'error' => 'The new passwords do not match.'], 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE admin_id = ? FOR UPDATE');
    $stmt->execute([(int)$admin['admin_id']]);
    $currentHash = $stmt->fetchColumn();
    if (!$currentHash) {
        $pdo->rollBack();
        password_change_reply(['ok' => false, 'error' => 'Admin account not found. Please sign in again.'], 404);
    }
    if (password_verify($password, (string)$currentHash)) {
        $pdo->rollBack();
        password_change_reply(['ok' => false, 'error' => 'Choose a password different from your temporary password.'], 422);
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE admin_id = ?');
    $update->execute([$newHash, (int)$admin['admin_id']]);
    $log = $pdo->prepare("INSERT INTO security_logs(admin_id,event_type,description,ip_address,user_agent) VALUES(?,'ADMIN_PASSWORD_CHANGED',?,?,?)");
    $log->execute([
        (int)$admin['admin_id'],
        'Administrator changed the temporary password.',
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);
    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['credential_version'] = hash('sha256', $newHash);
    $_SESSION['must_change_password'] = false;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    password_change_reply(['ok' => true, 'message' => 'Password changed. Opening the admin dashboard.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin temporary password change failed: ' . $e->getMessage());
    password_change_reply(['ok' => false, 'error' => 'Unable to change the password. Please try again.'], 500);
}
