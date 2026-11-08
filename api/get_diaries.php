<?php
// Returns the authenticated user's profile, theme, diaries, media,
// mood/weather options, and CSRF token.
require_once __DIR__ . '/../config/auth.php';
$userId = requireUser();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $stmtUser = $pdo->prepare('SELECT user_id, name, email, avatar_url FROM users WHERE user_id = ? AND is_active = 1 LIMIT 1');
    $stmtUser->execute([$userId]);
    $userRow = $stmtUser->fetch();

    if (!$userRow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'User not found.']);
        exit;
    }

    $stmtTheme = $pdo->prepare('SELECT t.code FROM user_settings us JOIN themes t ON t.theme_id = us.theme_id WHERE us.user_id = ? LIMIT 1');
    $stmtTheme->execute([$userId]);
    $themeCode = $stmtTheme->fetchColumn() ?: 'mint-choco';

    // --- diaries ---
    $stmt = $pdo->prepare("SELECT d.diary_id, d.content, d.diary_date, d.diary_time,
        m.emoji AS mood_emoji, m.code AS mood_code, m.label AS mood_label,
        w.emoji AS weather_emoji, w.code AS weather_code, w.label AS weather_label
        FROM diaries d
        LEFT JOIN moods m ON m.mood_id = d.mood_id
        LEFT JOIN weathers w ON w.weather_id = d.weather_id
        WHERE d.user_id = ?
        ORDER BY d.diary_date DESC, d.diary_time DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $mediaByDiary = [];
    $diaryIds = array_column($rows, 'diary_id');
    if ($diaryIds) {
        $in = implode(',', array_fill(0, count($diaryIds), '?'));
        $stmtMedia = $pdo->prepare("SELECT media_id, diary_id, file_path, file_type, sort_order, file_size, duration_sec
            FROM media WHERE diary_id IN ($in) ORDER BY diary_id, sort_order");
        $stmtMedia->execute($diaryIds);

        foreach ($stmtMedia->fetchAll() as $m) {
            $mediaByDiary[$m['diary_id']][] = [
                'id' => (int)$m['media_id'],
                'type' => $m['file_type'],
                'url' => $m['file_path'],
                'sortOrder' => (int)$m['sort_order'],
                'fileSize' => $m['file_size'] !== null ? (int)$m['file_size'] : null,
                'durationSec' => $m['duration_sec'] !== null ? (int)$m['duration_sec'] : null,
                'alt' => 'diary media',
            ];
        }
    }

    $diaries = [];
    foreach ($rows as $r) {
        $diaries[] = [
            'id' => (int)$r['diary_id'],
            'mood' => $r['mood_emoji'] ?: '😐',
            'moodCode' => $r['mood_code'] ?: 'neutral',
            'moodLabel' => $r['mood_label'] ?: 'Neutral',
            'weather' => $r['weather_emoji'] ?: '☁️',
            'weatherCode' => $r['weather_code'] ?: 'cloudy',
            'weatherLabel' => $r['weather_label'] ?: 'Cloudy',
            'content' => $r['content'] ?? '',
            'date' => $r['diary_date'] ? substr($r['diary_date'], 0, 10) : '',
            'time' => $r['diary_time'] ? substr($r['diary_time'], 0, 5) : '',
            'media' => $mediaByDiary[$r['diary_id']] ?? [],
        ];
    }

    // --- mood/weather options (from DB) ---
    $moods = $pdo->query('SELECT code, emoji, label FROM moods ORDER BY mood_id')->fetchAll();
    $weathers = $pdo->query('SELECT code, emoji, label FROM weathers ORDER BY weather_id')->fetchAll();

    $avatar = $userRow['avatar_url'] ?: '/mood_diary/assets/default-avatar.png';

    echo json_encode([
        'ok' => true,
        'csrf' => csrfToken(),
        'user' => [
            'id' => (int)$userRow['user_id'],
            'name' => $userRow['name'],
            'email' => $userRow['email'],
            'avatar' => $avatar,
        ],
        'theme' => $themeCode,
        'diaries' => $diaries,
        'moods' => array_map(fn($m) => [
            'code' => $m['code'], 'emoji' => $m['emoji'], 'label' => $m['label'],
        ], $moods),
        'weathers' => array_map(fn($w) => [
            'code' => $w['code'], 'emoji' => $w['emoji'], 'label' => $w['label'],
        ], $weathers),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('get_diaries failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load diary data.']);
}