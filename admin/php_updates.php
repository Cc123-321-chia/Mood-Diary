<?php
require_once __DIR__ . '/_auth.php';
requireAdmin();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PHP Update Monitor · Mood Diary Admin</title>
  <link rel="stylesheet" href="admin.css">
  <link rel="stylesheet" href="admin-logout.css">
  <link rel="stylesheet" href="php_updates.css">
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark">📔</span><span>Mood Diary<br><small class="muted">Admin workspace</small></span></a>
    <div class="side-label">Workspace</div>
    <nav class="nav" aria-label="Admin navigation">
      <a href="index.php"><span class="nav-icon">⌂</span> Dashboard</a>
      <a href="users.php"><span class="nav-icon">♙</span> Users</a>
      <a href="security_logs.php"><span class="nav-icon">◷</span> Security logs</a>
      <a class="active" href="php_updates.php"><span class="nav-icon">⬆</span> PHP update monitor</a>
    </nav>
    <div class="sidebar-note">Monitor official PHP release and security status. Updates are performed manually.</div>
    <button class="admin-logout" id="adminLogout" type="button">Log out</button>
    <p class="logout-error" id="logoutError" role="alert"></p>
  </aside>

  <main class="admin-main">
    <header class="page-heading">
      <div><h1>PHP Update Monitor</h1><p class="sub">Official release status and a manual XAMPP replacement guide</p></div>
    </header>
    <div id="notice" class="php-notice" hidden role="alert"></div>

    <section class="php-card panel" aria-labelledby="statusHeading">
      <div class="php-card-heading">
        <div><h2 id="statusHeading">PHP release status</h2><p>Detected from this web server’s PHP runtime</p></div>
        <span id="statusBadge" class="php-badge">Checking…</span>
      </div>
      <div class="php-metrics">
        <div><span>Web runtime version (Apache)</span><strong id="currentVersion">—</strong></div>
        <div><span>Latest on this branch</span><strong id="latestVersion">—</strong></div>
        <div><span>Release branch</span><strong id="releaseBranch">—</strong></div>
        <div><span>Support status</span><strong id="supportStatus">—</strong></div>
        <div><span>Latest release date</span><strong id="releaseDate">—</strong></div>
        <div><span>Last checked</span><strong id="lastChecked">—</strong></div>
        <div><span>Security update</span><strong id="securityStatus">—</strong></div>
        <div><span>Environment</span><strong id="environment">—</strong></div>
      </div>
      <p id="environmentGuidance" class="env-guidance"></p>
      <p class="source-line">Sources: <a href="https://www.php.net/releases/" target="_blank" rel="noopener">Official PHP releases</a> · <a href="https://www.php.net/supported-versions.php" target="_blank" rel="noopener">PHP support policy</a></p>
      <div class="php-actions">
        <button class="btn" id="checkButton" type="button">Check for Updates</button>
        <a class="btn" href="https://www.php.net/releases/" target="_blank" rel="noopener">View Release Details</a>
      </div>
      <p id="actionMessage" class="action-message" role="status"></p>
    </section>

    <section class="php-card panel guide-card" aria-labelledby="windowsGuideHeading">
      <h2 id="windowsGuideHeading">Windows XAMPP: replace the PHP folder manually</h2>
      <p class="guide-intro">Use this manual process to replace PHP in a Windows XAMPP installation. PHP updates are not run from this page. Your current web runtime is PHP 8.2.35; the cURL warning is from Apache failing to load the PHP cURL extension.</p>

      <div class="guide-warning" role="note"><strong>Back up before changing files</strong><p>Copy the whole XAMPP folder and export the <code>mood_diary</code> database. The Apache cURL error in your logs says the specified procedure could not be found. In this installation, Apache’s older <code>libssh2.dll</code> is conflicting with the one supplied with PHP. Step 7 below addresses that specific conflict.</p></div>
      <p class="guide-intro"><strong>Version and DLL names below are examples from this installation.</strong> Compare the files and version numbers on your own PC. If Apache’s log identifies a different conflicting PHP dependency, match that exact DLL to the same PHP package you installed; back up Apache’s existing copy first, replace only the named mismatch, then restart and verify. Do not copy every DLL or use a library from another PHP build.</p>

      <ol class="guide-steps">
        <li><h3>Completely stop XAMPP</h3><p>Stop Apache and MySQL/MariaDB from the XAMPP Control Panel, then close the panel. Open Task Manager with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>Esc</kbd> and confirm no XAMPP Apache process remains. Do not end unrelated processes.</p></li>
        <li><h3>Download the compatible PHP build</h3><p>Use the <a href="https://windows.php.net/download/" target="_blank" rel="noopener">official PHP for Windows downloads</a>. For Apache, choose the Thread Safe (TS), x64 ZIP with a compiler build compatible with your XAMPP. For example, if the monitor reports PHP 8.2.12, use the newest 8.2.x TS build offered for the matching compiler. Your current and target versions may be different; use the values shown for your installation. Verify the official SHA-256 checksum. Do not switch from PHP 8.2 to 8.5 by replacing only the PHP folder.</p></li>
        <li><h3>Preserve the existing PHP folder</h3><p>In <code>C:\xampp</code>, rename <code>php</code> to <code>php_old</code>. Keep it until the new runtime and your application have passed all checks.</p></li>
        <li><h3>Install the new PHP files</h3><p>Extract the downloaded ZIP, rename its extracted folder to <code>php</code>, and place it at <code>C:\xampp\php</code>. The new folder should contain <code>php.exe</code>, <code>ext</code>, <code>php8ts.dll</code>, and <code>php8apache2_4.dll</code>.</p></li>
        <li><h3>Restore PHP configuration</h3><p>Copy <code>C:\xampp\php_old\php.ini</code> into <code>C:\xampp\php\php.ini</code> if the old file exists. Open it and confirm <code>extension_dir="C:\xampp\php\ext"</code> and <code>extension=curl</code> are enabled. Review other extension settings because old extension DLLs may not work with the new PHP build.</p></li>
        <li><h3>Disable a missing browscap file if needed</h3><p>If <code>php.ini</code> points to a browscap file that does not exist, comment out the setting by adding a semicolon:</p><pre><code>;browscap="C:\xampp\php\extras\browscap.ini"</code></pre></li>
        <li><h3>Resolve the Apache cURL libssh2 conflict</h3><p>The <code>libssh2.dll</code> mismatch is the example found on this PC. First back up <code>C:\xampp\apache\bin\libssh2.dll</code> as <code>libssh2.dll.apache-old</code>. Then copy the matching <code>C:\xampp\php\libssh2.dll</code> into <code>C:\xampp\apache\bin</code>, replacing that specific Apache copy. For a different dependency named in your Apache log, apply the same process to that exact DLL using its counterpart from the same verified PHP package; do not replace unrelated Apache libraries. This aligns the DLL Apache finds first with the one shipped alongside PHP’s cURL extension. If Apache will not start or another module reports an error, stop Apache and restore the backed-up DLL. PHP’s cURL extension requires its matching <code>libssh2.dll</code> to be available to the web-server process. <a href="https://www.php.net/manual/en/curl.installation.php" target="_blank" rel="noopener">PHP cURL installation notes</a>.</p></li>
        <li><h3>Start XAMPP and verify PHP in Apache</h3><p>Start Apache in the XAMPP Control Panel. Run <code>C:\xampp\php\php.exe -v</code> to check CLI PHP, but verify the browser runtime separately. Create a temporary file in <code>C:\xampp\htdocs</code> containing <code>&lt;?php phpinfo(); ?&gt;</code>, open it through localhost, and confirm PHP version, loaded configuration file, and cURL. Delete that file immediately after checking because phpinfo exposes configuration details.</p></li>
        <li><h3>Check the app and keep your backups</h3><p>Test Mood Diary login, database access, diary pages, uploads, and admin pages. Check <code>C:\xampp\apache\logs\error.log</code> for PHP startup warnings. If Apache or PHP fails, stop Apache and restore the backed-up PHP folder and Apache DLL. Keep the full XAMPP and database backups until the app works normally.</p></li>
      </ol>
      <p class="guide-links">References: <a href="https://www.php.net/manual/en/install.windows.apache2.php" target="_blank" rel="noopener">PHP with Apache on Windows</a> · <a href="https://www.php.net/manual/en/curl.installation.php" target="_blank" rel="noopener">PHP cURL installation notes</a> · <a href="https://www.apachefriends.org/faq_windows.html" target="_blank" rel="noopener">Apache Friends XAMPP FAQ</a></p>
    </section>

    <section class="php-card panel guide-card" aria-labelledby="macGuideHeading">
      <h2 id="macGuideHeading">XAMPP for macOS</h2>
      <p>XAMPP for macOS bundles PHP with its Apache stack. The Windows PHP ZIP and folder steps above do not apply. Back up the XAMPP folder and database, then use the official XAMPP macOS package that includes the PHP version you need. See the <a href="https://www.apachefriends.org/faq_osx.html" target="_blank" rel="noopener">Apache Friends macOS FAQ</a>.</p>
    </section>
  </main>
</div>
<script src="php_updates.js?v=20261114-2" defer></script>
<script src="admin_logout.js"></script>
</body>
</html>
