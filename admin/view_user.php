<?php
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details | Mood Diary</title>
    <link rel="stylesheet" href="../css/view_user.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
  <link rel="stylesheet" href="admin-logout.css">
</head>

<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="index.php"><span class="admin-brand-mark">📔</span><span>Mood Diary<br><small>Admin workspace</small></span></a>
            <div class="admin-nav-label">Workspace</div>
            <nav class="admin-nav" aria-label="Admin navigation">
                <a href="index.php"><span class="admin-nav-icon">⌂</span> Dashboard</a>
                <a class="active" href="users.php"><span class="admin-nav-icon">♙</span> Users</a>
                <a href="security_logs.php"><span class="admin-nav-icon">◷</span> Security logs</a>
                <a href="php_updates.php"><span class="admin-nav-icon">⬆</span> PHP update monitor</a>
            </nav>
            <div class="admin-sidebar-note">Manage user accounts and review security activity.</div>
            <button class="admin-logout" id="adminLogout" type="button">Log out</button><p class="logout-error" id="logoutError" role="alert"></p>
  </aside>
        <main class="admin-content">
    <div class="container">

        <a href="users.php" class="back">
            &larr; Back to User Management
        </a>

        <div class="card">
                    <h1 id="detailsTitle">Account Details</h1>
            <p class="subtitle">
                View registered user information.
            </p>

            <div class="profile">
                <div class="avatar" id="userAvatar">🌲</div>

                <div>
                    <div class="profile-name" id="profileName">
                        Loading...
                    </div>

                    <div class="profile-email" id="profileEmail"></div>
                </div>
            </div>

            <div class="details">

                <div class="detail">
                    <div class="label">User ID</div>
                    <div class="value" id="userId">-</div>
                </div>

                <div class="detail">
                    <div class="label">Account Status</div>
                    <div id="userStatus">-</div>
                </div>

                <div class="detail">
                    <div class="label">Full Name</div>
                    <div class="value" id="userName">-</div>
                </div>

                <div class="detail">
                    <div class="label">Email Address</div>
                    <div class="value" id="userEmail">-</div>
                </div>

                <div class="detail">
                    <div class="label">Registration Date</div>
                    <div class="value" id="createdAt">-</div>
                </div>

                <div class="detail">
                    <div class="label">Last Updated</div>
                    <div class="value" id="updatedAt">-</div>
                </div>

            </div>
        </div>
    </div>
        </main>
    </div>

    <script src="../js/view_user.js?v=admin-account-view-2" defer></script>
  <script src="admin_logout.js"></script>
</body>
</html>
