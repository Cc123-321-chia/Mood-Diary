<?php
require_once __DIR__ . '/_diary_common.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$userId = requireUser();
requireCsrf();
$input = requireJsonPost();

$content     = trim((string)($input['content'] ?? ''));
$date        = (string)($input['date'] ?? '');
$time        = (string)($input['time'] ?? '');
$moodCode    = (string)($input['moodCode'] ?? 'neutral');
$weatherCode = (string)($input['weatherCode'] ?? 'cloudy');
$diaryId     = isset($input['diaryId']) && $input['diaryId'] !== null && $input['diaryId'] !== ''
    ? (int)$input['diaryId']
    : null;
$removedMediaIds = array_values(array_filter(
    array_map('intval', is_array($input['removedMediaIds'] ?? null) ? $input['removedMediaIds'] : []),
    fn($v) => $v > 0
));

if ($content === '' || mb_strlen($content) > 10000) {
    diaryJson(['ok' => false, 'error' => 'Diary content is required and must be under 10,000 characters.'], 422);
}
if (!diaryValidDate($date)) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary date.'], 422);
}
if ($date > (new DateTimeImmutable('today'))->format('Y-m-d')) {
    diaryJson(['ok' => false, 'error' => 'You cannot create a diary for a future date.'], 422);
}
if ($time !== '' && !diaryValidTime($time)) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary time.'], 422);
}

try {
    $stmtMood = $pdo->prepare('SELECT mood_id FROM moods WHERE code = ? LIMIT 1');
    $stmtMood->execute([$moodCode]);
    $moodId = $stmtMood->fetchColumn();

    $stmtWeather = $pdo->prepare('SELECT weather_id FROM weathers WHERE code = ? LIMIT 1');
    $stmtWeather->execute([$weatherCode]);
    $weatherId = $stmtWeather->fetchColumn();

    if (!$moodId || !$weatherId) {
        diaryJson(['ok' => false, 'error' => 'Invalid mood or weather selection.'], 422);
    }

    $pdo->beginTransaction();
    $isUpdate = $diaryId !== null;

    if ($isUpdate) {
        $owner = $pdo->prepare('SELECT diary_id FROM diaries WHERE diary_id = ? AND user_id = ? FOR UPDATE');
        $owner->execute([$diaryId, $userId]);
        if (!$owner->fetchColumn()) {
            $pdo->rollBack();
            diaryJson(['ok' => false, 'error' => 'Diary not found.'], 404);
        }

        $dup = $pdo->prepare('SELECT diary_id FROM diaries WHERE user_id = ? AND diary_date = ? AND diary_id <> ? LIMIT 1');
        $dup->execute([$userId, $date, $diaryId]);
        if ($dup->fetchColumn()) {
            $pdo->rollBack();
            diaryJson(['ok' => false, 'error' => 'You already have a diary entry for this date.'], 409);
        }

        $stmt = $pdo->prepare('UPDATE diaries SET mood_id = ?, weather_id = ?, content = ?, diary_date = ?, diary_time = ? WHERE diary_id = ? AND user_id = ?');
        $stmt->execute([(int)$moodId, (int)$weatherId, $content, $date, $time ?: null, $diaryId, $userId]);
    } else {
        $dup = $pdo->prepare('SELECT diary_id FROM diaries WHERE user_id = ? AND diary_date = ? LIMIT 1');
        $dup->execute([$userId, $date]);
        $existingId = $dup->fetchColumn();
        if ($existingId) {
            $pdo->rollBack();
            diaryJson([
                'ok' => false,
                'error' => 'You already have a diary entry for this date.',
                'existingDiaryId' => (int)$existingId,
            ], 409);
        }

        $stmt = $pdo->prepare('INSERT INTO diaries (user_id, mood_id, weather_id, content, diary_date, diary_time) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, (int)$moodId, (int)$weatherId, $content, $date, $time ?: null]);
        $diaryId = (int)$pdo->lastInsertId();
    }

    $pathsToDelete = [];
    if ($removedMediaIds && $diaryId) {
        $in = implode(',', array_fill(0, count($removedMediaIds), '?'));
        $stmtMedia = $pdo->prepare("SELECT m.media_id, m.file_path FROM media m JOIN diaries d ON d.diary_id = m.diary_id WHERE m.diary_id = ? AND d.user_id = ? AND m.media_id IN ($in)");
        $stmtMedia->execute(array_merge([$diaryId, $userId], $removedMediaIds));
        $ownedMedia = $stmtMedia->fetchAll();

        if ($ownedMedia) {
            $ownedIds = array_map('intval', array_column($ownedMedia, 'media_id'));
            $delIn = implode(',', array_fill(0, count($ownedIds), '?'));
            $pdo->prepare("DELETE FROM media WHERE diary_id = ? AND media_id IN ($delIn)")
                ->execute(array_merge([$diaryId], $ownedIds));
            $pathsToDelete = array_column($ownedMedia, 'file_path');
        }
    }

    $pdo->commit();

    foreach ($pathsToDelete as $path) {
        $absolute = diaryMediaAbsolutePath($path);
        if ($absolute && is_file($absolute)) @unlink($absolute);
    }

    diarySecurityLog($pdo, $userId, $isUpdate ? 'DIARY_UPDATE' : 'DIARY_CREATE', ($isUpdate ? 'Updated' : 'Created') . ' diary #' . $diaryId);
    diaryJson(['ok' => true, 'diaryId' => $diaryId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (($e->errorInfo[1] ?? null) === 1062) {
        diaryJson(['ok' => false, 'error' => 'You already have a diary entry for this date.'], 409);
    }
    error_log('save_diary DB error: ' . $e->getMessage());
    diaryJson(['ok' => false, 'error' => 'Could not save diary.'], 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save_diary failed: ' . $e->getMessage());
    diaryJson(['ok' => false, 'error' => 'Could not save diary.'], 500);
}