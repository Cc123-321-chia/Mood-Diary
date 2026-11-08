<?php
// api/delete_account.php
// Permanently deletes the user's account and all associated data:
//   - diaries, media records, user_settings, password_resets
//   - uploaded media files on disk
//   - the users row itself
// Requires the user to re-enter their password (prevents accidental deletion).

require_once __DIR__ . '/../config/auth.php';
$userId = requireUser();
requireCsrf();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$input    = requireJsonPost();
$password = is_string($input['password'] ?? null) ? $input['password'] : '';

if ($password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
    authError(422, 'Enter your password.');
}

try {
    $pdo->beginTransaction();

    // 1) Confirm the password before destroying anything.
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ? AND is_active = 1 FOR UPDATE');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash) {
        $pdo->rollBack();
        authError(404, 'Account not found.');
    }

    if (!password_verify($password, (string)$hash)) {
        $pdo->rollBack();
        writeSecurityLog($pdo, 'ACCOUNT_DELETE_FAILED', 'Wrong password on account delete attempt.', $userId);
        authError(401, 'Incorrect password.');
    }

    // 2) Collect media file paths BEFORE deleting their DB rows.
    $pathsStmt = $pdo->prepare(
        'SELECT m.file_path
         FROM media m
         JOIN diaries d ON d.diary_id = m.diary_id
         WHERE d.user_id = ?'
    );
    $pathsStmt->execute([$userId]);
    $mediaPaths = $pathsStmt->fetchAll(PDO::FETCH_COLUMN);

    // 3) Delete all related DB records (order matters).
    $pdo->prepare('DELETE FROM media WHERE diary_id IN (SELECT diary_id FROM diaries WHERE user_id = ?)')->execute([$userId]);
    $pdo->prepare('DELETE FROM diaries WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM user_settings WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

    // Anonymise security logs (keep the audit trail, drop the user link).
    $pdo->prepare('UPDATE security_logs SET user_id = NULL WHERE user_id = ?')->execute([$userId]);

    // 4) Log the deletion (before removing the user row, so the FK is still valid).
    writeSecurityLog($pdo, 'ACCOUNT_DELETED', 'User permanently deleted their account and all associated data.', $userId);

    // 5) Delete the user row itself.
    $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);

    $pdo->commit();

    // 6) Delete media files from disk (best effort).
    foreach ($mediaPaths as $path) {
        // Convert web path like /mood_diary/uploads/media/7/22/abc.jpg to absolute.
        $relative = preg_replace('#^/mood_diary/#', '', (string)$path);
        $absolute = __DIR__ . '/../' . ltrim($relative, '/');
        if (is_file($absolute)) @unlink($absolute);
    }

    // 7) Destroy the session.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete_account failed: ' . $e->getMessage());
    authError(500, 'Unable to delete account. Please try again later.');
}