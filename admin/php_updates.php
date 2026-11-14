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

    <section class="php-card panel guide-card" aria-labelledby="apacheGuideHeading">
      <h2 id="apacheGuideHeading">Windows XAMPP: update Apache manually</h2>
      <p class="guide-intro">This guide updates Apache itself. It does not install updates automatically and does not change the PHP version. The release monitor above checks PHP only; use Apache’s official security advisories to choose a patched Apache release.</p>

      <div class="guide-warning" role="note"><strong>Back up first and plan for a short outage</strong><p>Export the application database, then copy the entire <code>&lt;XAMPP_ROOT&gt;</code> folder to a separate location such as <code>&lt;XAMPP_ROOT&gt;_backup_before_apache_update</code>. Do not put the backup inside <code>&lt;XAMPP_ROOT&gt;</code>. Stop Apache and MySQL in the XAMPP Control Panel before replacing files. Keep the backup until Apache and the application have been verified.</p></div>
      <p class="guide-intro"><strong>Windows binaries and build compatibility:</strong> Apache HTTP Server publishes source releases; Apache Lounge provides Windows builds. Choose a supported 64-bit build compatible with your XAMPP architecture, loaded Apache modules, and PHP integration. Check the selected package's compiler/runtime requirements and install the matching Visual C++ Redistributable from <a href="https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist" target="_blank" rel="noopener">Microsoft's official download page</a>. Verify the archive against its publisher's checksum or signature. Release numbers and prerequisites change, so use the current Apache security advisories and release page.</p>
      <div class="guide-warning" role="note"><strong>Use your own installation details</strong><p>Find your XAMPP root (the folder containing <code>xampp-control.exe</code> and the <code>apache</code>, <code>php</code>, and database folders) and replace <code>&lt;XAMPP_ROOT&gt;</code> in these instructions with that path. Check the currently installed Apache version, the target release's supported Windows build, and its required runtime on the official release pages before you begin; do not copy another administrator's version numbers or backup paths.</p></div>

      <ol class="guide-steps">
        <li><h3>Confirm which Apache is running</h3><p>In an Administrator PowerShell window, inspect <code>httpd.exe</code> processes and the Apache service’s <code>PathName</code>. Run <code>&lt;XAMPP_ROOT&gt;\apache\bin\httpd.exe -v</code> to check that installation’s version. Do not update a different Apache directory from the one used by the service.</p><pre><code>Get-CimInstance Win32_Service | Where-Object { $_.Name -match 'Apache' } | Select-Object Name, State, ProcessId, PathName</code></pre></li>
        <li><h3>Select and verify a patched Windows build</h3><p>Review <a href="https://httpd.apache.org/security/vulnerabilities_24" target="_blank" rel="noopener">Apache’s 2.4 security advisories</a> and the <a href="https://www.apachelounge.com/download/" target="_blank" rel="noopener">Apache Lounge Windows downloads</a>. Choose the current supported 64-bit release. Match its build family to the loaded modules and install the Visual C++ Redistributable version required by that build from <a href="https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist" target="_blank" rel="noopener">Microsoft’s official download page</a>. Verify the archive against the publisher’s checksum or signature before extracting it.</p></li>
        <li><h3>Stop the stack and make the backup</h3><p>Export the database with phpMyAdmin or <code>mysqldump</code>. Stop Apache and MySQL/MariaDB in XAMPP, close the Control Panel, and confirm their processes have stopped. Copy all of <code>&lt;XAMPP_ROOT&gt;</code> to a separate backup folder. If you cannot stop a service or write the backup, pause here and resolve the permissions instead of copying live database files.</p></li>
        <li><h3>Stage the release and preserve XAMPP configuration</h3><p>Extract the verified archive to a temporary folder on the same disk. Keep the new release’s <code>bin</code>, <code>modules</code>, <code>include</code>, and <code>lib</code> directories together; Apache modules and libraries must come from the same release. Preserve XAMPP’s current <code>conf</code>, <code>logs</code>, and XAMPP helper scripts. Do not replace the new release’s modules with old module files.</p></li>
        <li><h3>Replace Apache while keeping rollback files</h3><p>Rename <code>&lt;XAMPP_ROOT&gt;\apache</code> to <code>apache_before_update</code>, then put the extracted Apache directory at <code>&lt;XAMPP_ROOT&gt;\apache</code>. Restore the backed-up XAMPP <code>conf</code> and <code>logs</code> directories and XAMPP helper scripts into the new folder. Keep the original folder until all checks pass. If PHP reports a specific missing or mismatched dependency in Apache’s error log, back up that DLL and replace only that named dependency with the matching file from the same PHP package; never copy unrelated DLLs.</p></li>
        <li><h3>Test the configuration before starting</h3><p>Open PowerShell as Administrator and run <code>&lt;XAMPP_ROOT&gt;\apache\bin\httpd.exe -t</code>. Continue only when it reports <code>Syntax OK</code>. If it reports a missing module, DLL, or directive, do not keep retrying with random files—use the exact error to correct the version/configuration mismatch or restore the backup.</p></li>
        <li><h3>Start Apache and verify the served version</h3><p>Run the XAMPP Control Panel as Administrator and start Apache. Confirm the Apache service is running, then run <code>&lt;XAMPP_ROOT&gt;\apache\bin\httpd.exe -v</code>. Test the site over localhost and check <code>&lt;XAMPP_ROOT&gt;\apache\logs\error.log</code>. Also verify the PHP runtime through Apache and test login, database access, diary pages, uploads, and admin pages. CLI <code>php -v</code> alone does not prove which Apache binary is serving requests.</p></li>
        <li><h3>Roll back if startup or health checks fail</h3><p>Stop Apache. Rename the failed new folder (for example, <code>apache_failed</code>) and rename <code>apache_before_update</code> back to <code>apache</code>. Start Apache again and confirm the previous version serves the site. Restore the database backup only if the update process changed or damaged database data. Keep both backups until the patched server is stable.</p></li>
      </ol>
      <div class="guide-warning" role="note"><strong>Reduce version details in response headers</strong><p>PHP may advertise its version in <code>X-Powered-By</code>; Apache identifies itself separately in the <code>Server</code> header. This project's <code>.htaccess</code> removes <code>X-Powered-By</code> with <code>mod_headers</code>. For a server-wide PHP setting, set <code>expose_php = Off</code> in the <code>php.ini</code> actually loaded by Apache. In the standard XAMPP layout this is usually <code>&lt;XAMPP_ROOT&gt;\php\php.ini</code>; confirm the loaded file for your installation before editing, then restart Apache. In the active Apache configuration, usually <code>&lt;XAMPP_ROOT&gt;\apache\conf\httpd.conf</code>, set <code>ServerTokens Prod</code> and <code>ServerSignature Off</code>. These reduce version details but do not replace security updates.</p><p>After restarting Apache, check the response in PowerShell. Replace the sample URL with your own HTTPS host and application path:</p><pre><code>$baseUrl = 'https://your-host/your-app/'&#10;curl.exe -sS -D - -o NUL $baseUrl</code></pre><p>For a local XAMPP site using a self-signed certificate only, add <code>-k</code> to the curl command. Do not use <code>-k</code> for a public site because it skips certificate verification. Expect <code>X-Powered-By</code> to be absent and Apache's <code>Server</code> header to show a generic product name, usually <code>Apache</code>, without its detailed version. If the old versions still appear, make sure you edited the configuration used by the Apache process serving the request.</p></div>
<div class="guide-warning" role="note"><strong>Get help if the upgrade fails</strong><p>If Apache fails its configuration check, will not start, or application health checks fail, stop and roll back using the backup rather than copying unverified DLLs or trying random builds. For XAMPP questions, visit the <a href="https://www.apachefriends.org/community" target="_blank" rel="noopener">Apache Friends community forums</a>. For Apache configuration, use the <a href="https://httpd.apache.org/userslist" target="_blank" rel="noopener">Apache HTTP Server Users mailing list</a>, a user-to-user community channel. For PHP setup, see <a href="https://www.php.net/support.php" target="_blank" rel="noopener">PHP community support</a>. If this is a production system, you cannot restore it safely, or the problem persists, ask a qualified system administrator or Apache/PHP professional. Share sanitized logs and version details only; never post passwords, tokens, private keys, or database dumps.</p></div>
      <p class="guide-links">References: <a href="https://httpd.apache.org/security/vulnerabilities_24" target="_blank" rel="noopener">Apache security advisories</a> · <a href="https://httpd.apache.org/docs/2.4/mod/core.html#servertokens" target="_blank" rel="noopener">Apache ServerTokens</a> · <a href="https://httpd.apache.org/docs/2.4/mod/core.html#serversignature" target="_blank" rel="noopener">Apache ServerSignature</a> · <a href="https://httpd.apache.org/download.cgi" target="_blank" rel="noopener">Apache source downloads</a> · <a href="https://www.apachelounge.com/download/" target="_blank" rel="noopener">Apache Lounge Windows builds</a> · <a href="https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist" target="_blank" rel="noopener">Microsoft Visual C++ Redistributable</a></p>
    </section>

    <section class="php-card panel guide-card" aria-labelledby="windowsGuideHeading">
      <h2 id="windowsGuideHeading">Windows XAMPP: replace the PHP folder manually</h2>
      <p class="guide-intro">Use this separate manual process to replace PHP in a Windows XAMPP installation. PHP updates are not run from this page. The version shown above is PHP as served by Apache; the command-line PHP version can differ.</p>

      <div class="guide-warning" role="note"><strong>Back up before changing files</strong><p>Copy the whole XAMPP folder and export the database configured for your application. Keep both backups until the new PHP runtime and your application have passed the checks below.</p></div>
      <p class="guide-intro"><strong>Use versions and DLL names that match your own installation.</strong> Check the PHP and Apache versions, architecture, compiler build, and enabled extensions before downloading. If Apache's log names a missing or mismatched DLL, use only the matching file from the same verified PHP package; back up the existing DLL before replacing it. Do not copy unrelated DLLs or use files from a different build.</p>

      <ol class="guide-steps">
        <li><h3>Completely stop XAMPP</h3><p>Stop Apache and MySQL/MariaDB from the XAMPP Control Panel, then close the panel. Open Task Manager with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>Esc</kbd> and confirm no XAMPP Apache process remains. Do not end unrelated processes.</p></li>
        <li><h3>Download the compatible PHP build</h3><p>Use the <a href="https://windows.php.net/download/" target="_blank" rel="noopener">official PHP for Windows downloads</a>. For Apache, choose a Thread Safe (TS), x64 ZIP whose PHP branch, compiler build, and architecture are compatible with your XAMPP and loaded Apache module. Use the latest supported patch in the branch you intend to keep; changing PHP major versions may require a compatible XAMPP/Apache stack, not just a folder swap. Verify the official SHA-256 checksum.</p></li>
        <li><h3>Preserve the existing PHP folder</h3><p>In <code>&lt;XAMPP_ROOT&gt;</code>, rename <code>php</code> to <code>php_old</code>. Keep it until the new runtime and your application have passed all checks.</p></li>
        <li><h3>Install the new PHP files</h3><p>Extract the downloaded ZIP, rename its extracted folder to <code>php</code>, and place it at <code>&lt;XAMPP_ROOT&gt;\php</code>. The new folder should contain <code>php.exe</code>, <code>ext</code>, <code>php8ts.dll</code>, and <code>php8apache2_4.dll</code>.</p></li>
        <li><h3>Restore PHP configuration</h3><p>Copy <code>&lt;XAMPP_ROOT&gt;\php_old\php.ini</code> into <code>&lt;XAMPP_ROOT&gt;\php\php.ini</code> if the old file exists. Open it and confirm <code>extension_dir="&lt;XAMPP_ROOT&gt;\php\ext"</code> and <code>extension=curl</code> are enabled. Review other extension settings because old extension DLLs may not work with the new PHP build.</p></li>
        <li><h3>Disable a missing browscap file if needed</h3><p>If <code>php.ini</code> points to a browscap file that does not exist, comment out the setting by adding a semicolon:</p><pre><code>;browscap="&lt;XAMPP_ROOT&gt;\php\extras\browscap.ini"</code></pre></li>
        <li><h3>Resolve only dependencies named by Apache</h3><p>If Apache's error log specifically names <code>libssh2.dll</code>, back up the Apache copy and replace only that file with its matching counterpart from the verified PHP package. Apply the same method to another DLL only when Apache names it and you have confirmed it belongs to the matching build. Never replace DLLs by guesswork. If Apache fails afterward, restore the backed-up file and consult the support links below.</p></li>
        <li><h3>Start XAMPP and verify PHP in Apache</h3><p>Start Apache in the XAMPP Control Panel. Run <code>&lt;XAMPP_ROOT&gt;\php\php.exe -v</code> to check CLI PHP, but verify the browser runtime separately. Create a temporary file in <code>&lt;XAMPP_ROOT&gt;\htdocs</code> containing <code>&lt;?php phpinfo(); ?&gt;</code>, open it through localhost, and confirm PHP version, loaded configuration file, and cURL. Delete that file immediately after checking because phpinfo exposes configuration details.</p></li>
        <li><h3>Check the app and keep your backups</h3><p>Test Mood Diary login, database access, diary pages, uploads, and admin pages. Check <code>&lt;XAMPP_ROOT&gt;\apache\logs\error.log</code> for PHP startup warnings. If Apache or PHP fails, stop Apache and restore the backed-up PHP folder and Apache DLL. Keep the full XAMPP and database backups until the app works normally.</p></li>
      </ol>
      <p class="guide-links">References: <a href="https://www.php.net/manual/en/install.windows.apache2.php" target="_blank" rel="noopener">PHP with Apache on Windows</a> · <a href="https://www.php.net/manual/en/curl.installation.php" target="_blank" rel="noopener">PHP cURL installation notes</a> · <a href="https://www.apachefriends.org/faq_windows.html" target="_blank" rel="noopener">Apache Friends XAMPP FAQ</a></p>
    </section>

    <section class="php-card panel guide-card" aria-labelledby="macGuideHeading">
      <h2 id="macGuideHeading">XAMPP for macOS</h2>
      <p>XAMPP for macOS bundles PHP with its Apache stack. The Windows PHP ZIP and folder steps above do not apply. Back up the XAMPP folder and database, then use the official XAMPP macOS package that includes the PHP version you need. See the <a href="https://www.apachefriends.org/faq_osx.html" target="_blank" rel="noopener">Apache Friends macOS FAQ</a>.</p>
    </section>
  </main>
</div>
<script src="php_updates.js?v=20261114-3" defer></script>
<script src="admin_logout.js"></script>
</body>
</html>
