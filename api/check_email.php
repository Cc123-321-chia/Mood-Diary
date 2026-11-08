<?php
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    authError(422, 'Enter a valid email address.');
}
require_once __DIR__ . '/../config/db.php';
try {
    // Only treat ACTIVE accounts as "already registered".
    // A hard-deleted user no longer exists in this table, so this just works.
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    echo json_encode(['ok' => true, 'registered' => (bool) $stmt->fetchColumn()]);
} catch (Throwable $e) {
    error_log('Mood Diary email check failed: ' . $e->getMessage());
    authError(500, 'Unable to check email. Please try again later.');
}