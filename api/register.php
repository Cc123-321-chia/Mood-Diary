<?php
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$input = requireJsonPost();
$name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
$email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$confirmation = is_string($input['confirmPassword'] ?? null) ? $input['confirmPassword'] : '';
if (preg_match('/\A.{1,50}\z/us', $name) !== 1) {
    authError(422, 'Enter a name between 1 and 50 characters.');
}
if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    authError(422, 'Enter a valid email address.');
}
if (!validPassword($password)) {
    authError(422, 'Use at least 8 characters, 1 special character and 1 uppercase letter (A–Z).');
}
// PASSWORD_DEFAULT uses bcrypt here; reject inputs it would truncate.
if (strlen($password) > 72 || strpos($password, "\0") !== false) {
    authError(422, 'Use a password of at most 72 UTF-8 bytes, without null characters.');
}
if ($password !== $confirmation) {
    authError(422, 'Passwords do not match.');
}
require_once __DIR__ . '/../config/db.php';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, 1)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    writeSecurityLog($pdo, 'USER_REGISTRATION', 'User account registered successfully: ' . $name . ' (' . $email . ').', $userId);
    $pdo->commit();
    // The dashboard supplies the default theme and avatar for new users.
    http_response_code(201);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (($e->errorInfo[1] ?? null) === 1062) {
        writeSecurityLog($pdo, 'USER_REGISTRATION_FAILED', 'Registration failed because the email address is already in use.');
        authError(409, 'An account with this email already exists. Please log in.');
    }
    error_log('Mood Diary registration failed: ' . $e->getMessage());
    writeSecurityLog($pdo, 'USER_REGISTRATION_FAILED', 'A user registration request could not be completed.');
    authError(500, 'Unable to create an account. Please try again later.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mood Diary registration failed: ' . $e->getMessage());
    writeSecurityLog($pdo, 'USER_REGISTRATION_FAILED', 'A user registration request could not be completed.');
    authError(500, 'Unable to create an account. Please try again later.');
}