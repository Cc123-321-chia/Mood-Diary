<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function diaryJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function diaryValidDate(string $value): bool {
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function diaryValidTime(string $value): bool {
    return (bool) preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value);
}

function diaryClientIp(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function diarySecurityLog(PDO $pdo, int $userId, string $eventType, string $description): void {
    writeSecurityLog($pdo, $eventType, $description, $userId);
}

function diaryMediaAbsolutePath(string $filePath): ?string {
    $prefix = '/mood_diary/uploads/media/';
    if (!str_starts_with($filePath, $prefix)) return null;

    $relative = substr($filePath, strlen($prefix));
    if ($relative === '' || str_contains($relative, '..')) return null;

    $base = realpath(__DIR__ . '/../uploads/media');
    if ($base === false) return null;

    $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $parent = realpath(dirname($candidate));
    if ($parent === false || !str_starts_with($parent, $base)) return null;

    return $candidate;
}
