<?php
// api/update_profile.php
// Receives a JSON body from the settings panel and updates:
//   - users.name        (display name)
//   - users.avatar_url  (avatar image, base64 data URI)
//   - user_settings.theme_id (selected theme, resolved from theme code)
// All updates run inside a single transaction so they either all succeed or all roll back.

require_once __DIR__ . '/../config/auth.php';
$userId = requireUser();

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// Read the raw JSON body sent by fetch(). Falls back to an empty array
// if the body is missing or invalid, so the file never crashes on bad input.
$input = requireJsonPost();
// [SECURITY INTEGRATION] Reuse the same session CSRF protection as diary mutations.
requireCsrf();

try {
    // Wrap all updates in a transaction: if anything fails, nothing is saved.
    $pdo->beginTransaction();

    // ------------------------------------------------------------
    // 1) Update the users table (name and/or avatar).
    //    Only the fields that were actually provided get included in the
    //    UPDATE, so partial saves (e.g. only theme) never overwrite other columns.
    // ------------------------------------------------------------
    $fields = [];
    $params = [];

    // Name: validate server-side; frontend checks are not a security boundary.
    if (isset($input['name'])) {
        if (!is_string($input['name'])) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            authError(422, 'Invalid name.');
        }

        $name = trim($input['name']);

        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            authError(422, 'Name must be between 1 and 50 characters.');
        }
        $fields[] = 'name = ?';
        $params[] = $name;
    }

    // Avatar: allow only a verified image data URI or the built-in default path.
    if (isset($input['avatar']) && $input['avatar'] !== '') {
        if (!is_string($input['avatar'])) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            authError(422, 'Invalid avatar image.');
        }

        $avatar = $input['avatar'];
        $isDefault = $avatar === '/mood_diary/assets/default-avatar.png';
        $isValidDataImage = false;

        if (preg_match('~^data:(image/(?:jpeg|png|webp|gif));base64,([A-Za-z0-9+/=\r\n]+)$~', $avatar, $matches)) {
            $binary = base64_decode($matches[2], true);
            if ($binary !== false && strlen($binary) <= 2 * 1024 * 1024) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->buffer($binary);
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $isValidDataImage = in_array($detected, $allowed, true) && $detected === $matches[1];
            }
        }

        if (!$isDefault && !$isValidDataImage) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            authError(422, 'Invalid avatar image.');
        }

        $fields[] = 'avatar_url = ?';
        $params[] = $avatar;
    }

    // Only run the UPDATE when at least one field was provided.
    if ($fields) {
        $params[] = $userId;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?");
        $stmt->execute($params);
    }

    // ------------------------------------------------------------
    // 2) Update the user's theme.
    //    The client sends a theme CODE (e.g. "ocean"), but user_settings
    //    stores theme_id, so we resolve it first.
    //    Upsert (INSERT ... ON DUPLICATE KEY UPDATE) handles both cases:
    //      - user_settings row exists  -> update theme_id
    //      - user_settings row missing -> create it
    // ------------------------------------------------------------
    if (isset($input['theme']) && $input['theme'] !== '') {
        if (!is_string($input['theme'])) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            authError(422, 'Invalid theme selection.');
        }

        $stmt = $pdo->prepare("SELECT theme_id FROM themes WHERE code = ? AND is_active = 1");
        $stmt->execute([$input['theme']]);
        $themeId = $stmt->fetchColumn();

        if (!$themeId) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            authError(422, 'Invalid theme selection.');
        }

        $pdo->prepare("
            INSERT INTO user_settings (user_id, theme_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE theme_id = VALUES(theme_id)
        ")->execute([$userId, $themeId]);
    }

    // Commit only after both blocks succeeded.
    $pdo->commit();
    $changed = [];
    if (isset($input['name'])) $changed[] = 'name';
    if (isset($input['avatar']) && $input['avatar'] !== '') $changed[] = 'avatar';
    if (!empty($input['theme'])) $changed[] = 'theme';
    if ($changed) writeSecurityLog($pdo, 'PROFILE_UPDATED', 'Updated profile fields: ' . implode(', ', $changed) . '.', $userId);
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    // Roll back any partially applied changes, then return a JSON error.
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update_profile failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to update profile. Please try again later.']);
}
