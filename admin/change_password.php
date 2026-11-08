<?php
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin();
if (empty($_SESSION['must_change_password'])) {
    header('Location: index.php');
    exit;
}
$csrfToken = htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Change temporary password · Mood Diary Admin</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <main class="login-card">
    <div class="brand">📔 Mood Diary · Admin</div>
    <h1>Choose a new password</h1>
    <p class="intro">You signed in with a temporary password. Change it now to continue to the admin dashboard.</p>
    <form id="changePasswordForm" action="api/change_password.php" method="post">
      <input type="hidden" id="csrfToken" value="<?= $csrfToken ?>">
      <label for="newPassword">New password</label>
      <div class="password-field">
        <input id="newPassword" type="password" autocomplete="new-password" required aria-describedby="passwordRequirements">
        <button id="togglePassword" type="button" aria-label="Show password" aria-pressed="false">Show</button>
      </div>
      <ul id="passwordRequirements" class="requirements">
        <li id="ruleLength">At least 8 characters</li>
        <li id="ruleSpecial">At least 1 special character (e.g. ! @ # $)</li>
        <li id="ruleUppercase">At least 1 uppercase letter (A–Z)</li>
      </ul>
      <label for="confirmPassword">Confirm new password</label>
      <input id="confirmPassword" type="password" autocomplete="new-password" required>
      <p id="changePasswordMessage" class="error" role="alert"></p>
      <button class="submit" id="changePasswordButton" type="submit">Change password</button>
    </form>
    <button class="submit" id="adminLogout" type="button" style="margin-top:12px;background:#e3efe9;color:#1f3a36">Log out</button>
    <p class="error" id="logoutError" role="alert"></p>
  </main>
  <script src="change_password.js"></script>
  <script src="admin_logout.js"></script>
</body>
</html>
