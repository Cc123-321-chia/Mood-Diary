<?php
require_once __DIR__ . '/security_log.php';
// Shared session and password validation for user authentication.
function startUserSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('MOOD_USER_SID');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
        'path' => '/mood_diary/',
    ]);
    session_start();
    header('Cache-Control: no-store');
}
function authError(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// [DIARY CRUD SECURITY] CSRF token shared by diary/profile state-changing requests.
function csrfToken(): string {
    startUserSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void {
    startUserSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        authError(403, 'Invalid security token. Refresh the page and try again.');
    }
}
function validPassword(string $password): bool {
    return preg_match('/\A.{8,}\z/us', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[\p{P}\p{S}]/u', $password) === 1;
}
function requireUser(): int {
    startUserSession();
    if (empty($_SESSION['user_id']) || ($_SESSION['expires_at'] ?? 0) < time()) {
        $_SESSION = [];
        authError(401, 'Please log in to continue.');
    }
    // Password changes revoke existing sessions, including on other devices.
    global $pdo;
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !hash_equals(hash('sha256', $hash), $_SESSION['credential_version'] ?? '')) {
        $_SESSION = [];
        authError(401, 'Please log in again.');
    }
    return (int) $_SESSION['user_id'];
}
function requireJsonPost(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        authError(405, 'Please use POST.');
    }
    // JSON-only requests prevent cross-origin HTML form submissions.
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        authError(415, 'Please send JSON.');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) authError(400, 'Invalid request.');
    return $input;
}
