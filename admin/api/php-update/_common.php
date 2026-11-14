<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function phpUpdateAdmin(): array
{
    return requireAdmin(true);
}

function phpUpdateReply(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function phpUpdateRequireCsrf(array $input): void
{
    startAdminSession();
    $token = is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : '';
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        phpUpdateReply(['ok' => false, 'error' => 'Refresh the page and try again.'], 403);
    }
}

function phpUpdateEnvironment(): array
{
    $os = PHP_OS_FAMILY;
    $binary = strtolower(str_replace('\\', '/', PHP_BINARY));
    $documentRoot = strtolower(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')));
    if ($os === 'Darwin' && (str_contains($binary, '/applications/xampp/') || str_contains($documentRoot, '/applications/xampp/'))) {
        return ['label' => 'XAMPP for macOS', 'guidance' => 'Monitor releases here. Use the official XAMPP for macOS package to update its bundled PHP and Apache stack.'];
    }
    if ($os === 'Windows' && (str_contains($binary, '/xampp/') || str_contains($documentRoot, '/xampp/'))) {
        return ['label' => 'Windows + XAMPP', 'guidance' => 'Updates are not installed by this page. Follow the manual XAMPP PHP replacement guide below and verify the PHP build and Apache DLL dependencies first.'];
    }
    if ($os === 'Windows') return ['label' => 'Windows ' . PHP_SAPI, 'guidance' => 'Use the installation vendor’s documented PHP update process. This page only monitors releases.'];
    if (is_file('/.dockerenv')) return ['label' => 'Docker container (' . $os . ')', 'guidance' => 'This page only monitors releases. Update the pinned PHP base image through your deployment process.'];
    if ($os === 'Linux') return ['label' => 'Linux (' . PHP_SAPI . ')', 'guidance' => 'This page only monitors releases. Use your operating system package manager to update PHP.'];
    return ['label' => $os . ' (' . PHP_SAPI . ')', 'guidance' => 'This page monitors PHP releases only; use the platform administrator’s update process.'];
}

function phpUpdateSchemaReady(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'php_update_settings'");
    return (int)$stmt->fetchColumn() === 1;
}

function phpUpdateSettings(PDO $pdo): array
{
    $row = $pdo->query('SELECT auto_check_enabled, check_interval_hours, last_check_at, cached_payload FROM php_update_settings WHERE settings_id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->exec('INSERT IGNORE INTO php_update_settings (settings_id) VALUES (1)');
        $row = $pdo->query('SELECT auto_check_enabled, check_interval_hours, last_check_at, cached_payload FROM php_update_settings WHERE settings_id = 1')->fetch(PDO::FETCH_ASSOC);
    }
    return $row ?: [];
}

function phpUpdateFetchOfficial(): array
{
    if (!extension_loaded('curl')) throw new RuntimeException('The PHP cURL extension is required for secure update checks.');
    $fetchJson = static function (string $url): array {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Could not initialize the secure update check.');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'MoodDiary-PHPUpdateMonitor/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_MAXFILESIZE => 1048576,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status !== 200 || !str_contains($contentType, 'application/json') || strlen($body) > 1048576) {
            error_log('Mood Diary PHP release monitoring failed: ' . substr($error ?: ('official feed returned HTTP ' . $status . ' (' . $contentType . ')'), 0, 200));
            throw new RuntimeException('The official PHP release feed could not be verified. Try again later.');
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('Mood Diary PHP update feed returned invalid JSON.');
            throw new RuntimeException('The official PHP release feed returned invalid JSON.');
        }
        if (!is_array($data)) throw new RuntimeException('The official PHP release feed had an unexpected format.');
        return $data;
    };

    $supportFeed = $fetchJson('https://www.php.net/releases/index.php?json&max=1');
    $releaseUrl = 'https://www.php.net/releases/index.php?json&version=8&max=100';
    $feed = $fetchJson($releaseUrl);
    $supported = $supportFeed['8']['supported_versions'] ?? null;
    if (!is_array($supported) || count($supported) > 10) throw new RuntimeException('The official PHP feed had invalid support metadata.');
    $supported = array_values(array_filter($supported, static fn($v) => is_string($v) && preg_match('/\A[0-9]+\.[0-9]+\z/', $v) === 1));
    if (!$supported) throw new RuntimeException('The official PHP release feed did not list supported branches.');

    $current = PHP_VERSION;
    if (preg_match('/\A([0-9]+\.[0-9]+)\.[0-9]+/', $current, $m) !== 1) throw new RuntimeException('The installed PHP version could not be validated.');
    $branch = $m[1];
    $latest = null;
    $security = false;
    $globalLatest = null;
    foreach ($feed as $version => $release) {
        if (!is_string($version) || preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $version) !== 1 || !is_array($release)) continue;
        $release['version'] = $version;
        if ($globalLatest === null || version_compare($version, $globalLatest['version'], '>')) $globalLatest = $release;
        if (!str_starts_with($version, $branch . '.')) continue;
        if ($latest === null || version_compare($release['version'], $latest['version'], '>')) $latest = $release;
        if (version_compare($release['version'], $current, '>') && in_array('security', $release['tags'] ?? [], true)) $security = true;
    }
    $isSupported = in_array($branch, $supported, true);
    if (!$isSupported && $latest === null) $latest = $globalLatest;
    if ($isSupported && $latest === null) throw new RuntimeException('The official PHP release feed did not include the installed supported branch.');
    $isNewer = $latest !== null && version_compare($latest['version'], $current, '>');
    $environment = phpUpdateEnvironment();
    return [
        'payload_version' => 1,
        'current_version' => $current,
        'latest_version' => $latest['version'] ?? null,
        'release_date' => is_string($latest['date'] ?? null) ? $latest['date'] : null,
        'release_branch' => $branch,
        'supported' => $isSupported,
        'security_update' => $security,
        'update_available' => $isNewer,
        'status' => !$isSupported ? 'unsupported' : ($security ? 'security_update' : ($isNewer ? 'update_available' : 'up_to_date')),
        'source' => $releaseUrl,
        'checked_at' => date('Y-m-d H:i:s'),
        'environment' => $environment,
        'release_tags' => array_values(array_filter($latest['tags'] ?? [], static fn($v) => is_string($v) && preg_match('/\A[a-z_-]{1,30}\z/i', $v) === 1)),
    ];
}

function phpUpdateValidPayload($payload): bool
{
    if (!is_array($payload) || ($payload['payload_version'] ?? null) !== 1) return false;
    if (!is_string($payload['current_version'] ?? null) || preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $payload['current_version']) !== 1) return false;
    if (!is_string($payload['release_branch'] ?? null) || preg_match('/\A[0-9]+\.[0-9]+\z/', $payload['release_branch']) !== 1) return false;
    if (!is_bool($payload['supported'] ?? null) || !is_bool($payload['security_update'] ?? null) || !is_bool($payload['update_available'] ?? null)) return false;
    if (!in_array($payload['status'] ?? null, ['up_to_date', 'update_available', 'security_update', 'unsupported'], true)) return false;
    if (($payload['latest_version'] ?? null) !== null && (!is_string($payload['latest_version']) || preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $payload['latest_version']) !== 1)) return false;
    if (!is_array($payload['environment'] ?? null) || !is_string($payload['environment']['label'] ?? null) || !is_string($payload['environment']['guidance'] ?? null)) return false;
    return is_string($payload['checked_at'] ?? null) && is_string($payload['source'] ?? null) && str_starts_with($payload['source'], 'https://www.php.net/');
}

function phpUpdateCache(PDO $pdo, array $payload): void
{
    $stmt = $pdo->prepare('UPDATE php_update_settings SET last_check_at = NOW(), cached_payload = ? WHERE settings_id = 1');
    $stmt->execute([json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)]);
}

function phpUpdateLoadState(PDO $pdo, bool $force = false): array
{
    $settings = phpUpdateSettings($pdo);
    $payload = is_string($settings['cached_payload'] ?? null) ? json_decode($settings['cached_payload'], true) : null;
    if (!phpUpdateValidPayload($payload)) $payload = null;
    $current = PHP_VERSION;
    if (preg_match('/\A([0-9]+\.[0-9]+)\.[0-9]+/', $current, $match) !== 1) throw new RuntimeException('The installed PHP version could not be validated.');
    $branch = $match[1];
    $branchChanged = $payload !== null && (($payload['release_branch'] ?? '') !== $branch || (is_string($payload['latest_version'] ?? null) && !str_starts_with($payload['latest_version'], $branch . '.')));
    $hours = max(1, min(168, (int)($settings['check_interval_hours'] ?? 24)));
    $lastCheck = strtotime((string)($settings['last_check_at'] ?? '')) ?: 0;
    $shouldCheck = $force || !$payload || $branchChanged || ((int)($settings['auto_check_enabled'] ?? 1) === 1 && time() - $lastCheck >= $hours * 3600);
    $checkError = null;
    if ($shouldCheck) {
        try {
            $payload = phpUpdateFetchOfficial();
            phpUpdateCache($pdo, $payload);
            $settings['last_check_at'] = $payload['checked_at'];
        } catch (Throwable $e) {
            $checkError = $e->getMessage();
            if (!$payload || $branchChanged) throw $e;
        }
    }
    if (!is_array($payload)) throw new RuntimeException('No PHP release status is cached.');
    $payload['current_version'] = $current;
    $payload['release_branch'] = $branch;
    $hasNewerRelease = is_string($payload['latest_version'] ?? null) && version_compare($payload['latest_version'], $current, '>');
    $payload['update_available'] = $hasNewerRelease;
    $payload['security_update'] = $hasNewerRelease && !empty($payload['security_update']);
    $payload['status'] = empty($payload['supported']) ? 'unsupported' : ($payload['security_update'] ? 'security_update' : ($hasNewerRelease ? 'update_available' : 'up_to_date'));
    $payload['checked_at'] = (string)($settings['last_check_at'] ?? $payload['checked_at'] ?? '');
    $payload['environment'] = phpUpdateEnvironment();
    return ['status' => $payload, 'settings' => ['auto_check_enabled' => (bool)($settings['auto_check_enabled'] ?? true), 'check_interval_hours' => $hours, 'last_check_at' => $settings['last_check_at'] ?? null], 'check_error' => $checkError];
}
