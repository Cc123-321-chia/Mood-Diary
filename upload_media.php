<?php
require_once __DIR__ . '/_diary_common.php';

$userId = requireUser();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    diaryJson(['ok' => false, 'error' => 'Please use POST.'], 405);
}

// Detect a POST body that PHP silently dropped because it exceeded post_max_size.
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    diaryJson([
        'ok' => false,
        'error' => 'Upload too large for server limit. Please reduce file size or count.',
        'rejected' => [],
    ], 413);
}

$rawDiaryId = $_POST['diary_id'] ?? null;

if (
    !is_string($rawDiaryId) ||
    !preg_match('/^[1-9][0-9]*$/D', $rawDiaryId)
) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary ID.'], 422);
}

$diaryId = filter_var($rawDiaryId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($diaryId === false) {
    diaryJson(['ok' => false, 'error' => 'Invalid diary ID.'], 422);
}

$check = $pdo->prepare('SELECT diary_id FROM diaries WHERE diary_id = ? AND user_id = ? LIMIT 1');
$check->execute([$diaryId, $userId]);
if (!$check->fetchColumn()) diaryJson(['ok' => false, 'error' => 'Diary not found.'], 404);

if (!isset($_FILES['files'])) {
    // A non-zero CONTENT_LENGTH with no parsed files means parsing failed.
    if ($contentLength > 0) {
        diaryJson(['ok' => false, 'error' => 'No files could be parsed from the request.'], 422);
    }
    diaryJson(['ok' => true, 'uploaded' => [], 'rejected' => []]);
}

$files = $_FILES['files'];
$count = is_array($files['name']) ? count($files['name']) : 0;
if ($count < 1) diaryJson(['ok' => true, 'uploaded' => [], 'rejected' => []]);

// Optional per-file durations, aligned by index with files[].
$rawDurations = $_POST['durations'] ?? [];

if (!is_array($rawDurations)) {
    diaryJson(['ok' => false, 'error' => 'Invalid media durations.'], 422);
}

$durations = [];

foreach (array_values($rawDurations) as $value) {
    if ($value === '') {
        $durations[] = null;
        continue;
    }

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/D', $value)
    ) {
        diaryJson(['ok' => false, 'error' => 'Invalid media duration.'], 422);
    }

    $duration = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if ($duration === false) {
        diaryJson(['ok' => false, 'error' => 'Invalid media duration.'], 422);
    }

    $durations[] = $duration;
}

$maxMedia = 9;
$maxImage = 8 * 1024 * 1024;
$maxVideo = 40 * 1024 * 1024;
$maxTotal = 80 * 1024 * 1024;

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM media WHERE diary_id = ?');
$stmtCount->execute([$diaryId]);
$existingCount = (int)$stmtCount->fetchColumn();
$capacity = $maxMedia - $existingCount;

if ($capacity <= 0) {
    diaryJson([
        'ok' => false,
        'error' => 'Already at maximum 9 media files for this diary.',
        'uploaded' => [],
        'rejected' => [],
    ], 422);
}

$allowed = [
    'image/jpeg' => ['image', 'jpg'],
    'image/png'  => ['image', 'png'],
    'image/webp' => ['image', 'webp'],
    'image/gif'  => ['image', 'gif'],
    'video/mp4'  => ['video', 'mp4'],
    'video/webm' => ['video', 'webm'],
];

// Some environments report mp4/webm as these MIME types; resolve by extension.
$videoMimeFallbacks = [
    'video/quicktime'          => ['video', 'mp4'],
    'application/octet-stream' => null, // resolved below by extension only
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$prepared = [];
$rejected = [];
$totalBytes = 0;

for ($i = 0; $i < $count; $i++) {
    $name = $files['name'][$i] ?? ('file_' . $i);

    if (count($prepared) >= $capacity) {
        $rejected[] = $name . ' (over the ' . $maxMedia . '-file limit)';
        continue;
    }

    $error = (int)$files['error'][$i];
    if ($error !== UPLOAD_ERR_OK) {
        $rejected[] = $name . ' (upload error ' . $error . ')';
        continue;
    }

    $tmp  = $files['tmp_name'][$i];
    $size = (int)$files['size'][$i];

    if (!is_uploaded_file($tmp)) {
        $rejected[] = $name . ' (invalid upload)';
        continue;
    }

    $mime = $finfo->file($tmp) ?: '';

    // Resolve MIME, with fallbacks for common video misdetections.
    $resolved = null;
    if (isset($allowed[$mime])) {
        $resolved = $allowed[$mime];
    } else {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($mime === 'video/quicktime' && $ext === 'mp4') {
            $resolved = ['video', 'mp4'];
        }
    }

    if (!$resolved) {
        $rejected[] = $name . ' (unsupported type ' . $mime . ')';
        continue;
    }

    [$type, $ext] = $resolved;
    $limit = $type === 'image' ? $maxImage : $maxVideo;
    if ($size <= 0 || $size > $limit) {
        $rejected[] = $name . ' (too large; max ' . ($limit / 1024 / 1024) . ' MB)';
        continue;
    }

    if ($totalBytes + $size > $maxTotal) {
        $rejected[] = $name . ' (total size would exceed ' . ($maxTotal / 1024 / 1024) . ' MB)';
        continue;
    }

    $totalBytes += $size;
    $duration = $durations[$i] ?? null;
    $prepared[] = [
        'tmp'      => $tmp,
        'size'     => $size,
        'type'     => $type,
        'ext'      => $ext,
        'duration' => $duration,
    ];
}

if (!$prepared) {
    diaryJson([
        'ok' => false,
        'error' => 'No files could be accepted.',
        'uploaded' => [],
        'rejected' => $rejected,
    ], 422);
}

$dir = __DIR__ . '/../uploads/media/' . $userId . '/' . $diaryId;
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    diaryJson(['ok' => false, 'error' => 'Could not prepare upload folder.'], 500);
}

$uploaded = [];
$createdFiles = [];

try {
    $pdo->beginTransaction();

    $stmtOrder = $pdo->prepare('SELECT sort_order FROM media WHERE diary_id = ? ORDER BY sort_order FOR UPDATE');
    $stmtOrder->execute([$diaryId]);
    $usedOrders = array_map('intval', $stmtOrder->fetchAll(PDO::FETCH_COLUMN));
    $availableOrders = array_values(array_diff(range(0, 8), $usedOrders));
    if (count($availableOrders) < count($prepared)) {
        throw new RuntimeException('Not enough sort_order slots.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO media (diary_id, file_path, file_type, sort_order, file_size, duration_sec) VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($prepared as $index => $file) {
        $sort = $availableOrders[$index];
        $stored = bin2hex(random_bytes(16)) . '.' . $file['ext'];
        $absolute = $dir . DIRECTORY_SEPARATOR . $stored;

        if (!move_uploaded_file($file['tmp'], $absolute)) {
            $rejected[] = 'Could not save file to disk.';
            continue;
        }
        $createdFiles[] = $absolute;

        $webPath = '/mood_diary/uploads/media/' . $userId . '/' . $diaryId . '/' . $stored;
        $insert->execute([$diaryId, $webPath, $file['type'], $sort, $file['size'], $file['duration']]);
        $uploaded[] = [
            'id'        => (int)$pdo->lastInsertId(),
            'type'      => $file['type'],
            'url'       => $webPath,
            'sortOrder' => $sort,
        ];
    }

    if (!$uploaded) {
        $pdo->rollBack();
        foreach ($createdFiles as $p) if (is_file($p)) @unlink($p);
        diaryJson([
            'ok' => false,
            'error' => 'No files were saved.',
            'uploaded' => [],
            'rejected' => $rejected,
        ], 500);
    }

    $pdo->commit();
    diarySecurityLog($pdo, $userId, 'MEDIA_UPLOAD', 'Uploaded ' . count($uploaded) . ' media file(s) to diary #' . $diaryId);
    diaryJson([
        'ok' => true,
        'uploaded' => $uploaded,
        'rejected' => $rejected,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($createdFiles as $path) if (is_file($path)) @unlink($path);
    error_log('upload_media failed: ' . $e->getMessage());
    diaryJson(['ok' => false, 'error' => 'Could not upload media.'], 500);
}