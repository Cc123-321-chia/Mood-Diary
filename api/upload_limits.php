<?php
require_once __DIR__ . '/_diary_common.php';

$userId = requireUser();
// GET requires login but not CSRF

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    diaryJson(['ok' => false, 'error' => 'Please use GET.'], 405);
}

/**
 * Convert a php.ini shorthand size (e.g. "8M", "1G") to bytes.
 */
function iniBytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $unit = strtolower($val[strlen($val) - 1]);
    $num = (float)$val;
    switch ($unit) {
        case 'g': $num *= 1024 * 1024 * 1024; break;
        case 'm': $num *= 1024 * 1024; break;
        case 'k': $num *= 1024; break;
        default:  $num = (float)$val; break;
    }
    return (int)$num;
}

$uploadMax = iniBytes(ini_get('upload_max_filesize'));
$postMax   = iniBytes(ini_get('post_max_size'));
$maxFiles  = (int)ini_get('max_file_uploads');

// Effective single-file ceiling = min(upload_max_filesize, post_max_size).
// post_max_size includes multipart overhead, so use 95% as a safe margin.
$postSafe = $postMax > 0 ? (int)floor($postMax * 0.95) : 0;
$candidates = array_filter([$uploadMax, $postSafe], fn($v) => $v > 0);
$singleMax = $candidates ? min($candidates) : 0;

diaryJson([
    'ok' => true,
    'uploadMaxFilesize' => $uploadMax,
    'postMaxSize'       => $postMax,
    'maxFileUploads'    => $maxFiles,
    'singleFileMax'     => $singleMax,
    'maxImageBytes'     => 8 * 1024 * 1024,
    'maxVideoBytes'     => 40 * 1024 * 1024,
    'maxMedia'          => 9,
]);