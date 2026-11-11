<?php
require_once __DIR__ . '/_diary_common.php';

$userId = requireUser();
requireCsrf();
$input = requireJsonPost();
$rawDiaryId = $input['diaryId'] ?? null;

if (
    !is_int($rawDiaryId) &&
    !(is_string($rawDiaryId) && preg_match('/^[1-9][0-9]*$/D', $rawDiaryId))
) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary ID.'], 422);
}

$diaryId = filter_var($rawDiaryId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($diaryId === false) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary ID.'], 422);
}

try {
    $pdo->beginTransaction();

    // Object-level authorization: ID alone is never enough; user_id must match.
    $stmt = $pdo->prepare('SELECT diary_id FROM diaries WHERE diary_id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$diaryId, $userId]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        diaryJson(['ok' => false, 'error' => 'Diary not found.'], 404);
    }

    $m = $pdo->prepare('SELECT file_path FROM media WHERE diary_id = ?');
    $m->execute([$diaryId]);
    $paths = $m->fetchAll(PDO::FETCH_COLUMN);

    $del = $pdo->prepare('DELETE FROM diaries WHERE diary_id = ? AND user_id = ?');
    $del->execute([$diaryId, $userId]);
    $pdo->commit();

    foreach ($paths as $path) {
        $absolute = diaryMediaAbsolutePath($path);
        if ($absolute && is_file($absolute)) @unlink($absolute);
    }

    diarySecurityLog($pdo, $userId, 'DIARY_DELETE', 'Deleted diary #' . $diaryId);
    diaryJson(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete_diary failed: ' . $e->getMessage());
    diaryJson(['ok' => false, 'error' => 'Could not delete diary.'], 500);
}
