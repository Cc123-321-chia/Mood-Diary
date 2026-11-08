
<?php
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Mood Diary</title>
    <link rel="stylesheet" href="../css/users.css">
    <link rel="stylesheet" href="../css/admin_sidebar.css">
    <style>
        .modal-content select,.modal-content input[type="password"],.modal-content input[readonly]{width:100%;padding:11px;border:1px solid #c2d9d2;border-radius:8px;font:inherit}
        .account-pages{display:flex;justify-content:space-between;align-items:center;gap:12px;padding-top:16px;color:#55736b}
        .account-pages>div{display:flex;gap:8px}
        .pending-row td{padding:8px 14px;background:#fff8e8;color:#785b26;font-size:13px}
        .btn:disabled{opacity:.55;cursor:wait}
        .password-requirements{margin:8px 0 0;padding:0;font-size:13px;color:#45665e}
        .password-requirements p{margin:0 0 6px}
        .password-requirements ul{list-style:none;margin:0;padding:0;display:grid;gap:5px}
        .password-requirements li{display:flex;align-items:center;gap:10px;color:#45665e;line-height:1.6}
        .password-requirements li::before{content:"○";color:#45665e;font-size:17px;flex-shrink:0}
        .password-requirements li.met{color:#21845b}
        .password-requirements li.met::before{content:"✓";color:#21845b;font-size:17px;font-weight:600}
        @media(max-width:600px){.account-pages{align-items:stretch;flex-direction:column}}
    </style>
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
            </nav>
            <div class="admin-sidebar-note">Manage user accounts and review security activity.</div>
            <button class="admin-logout" id="adminLogout" type="button">Log out</button>
            <p class="logout-error" id="logoutError" role="alert"></p>
        </aside>

        <main class="admin-content">
            <div class="container">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                    <div>
                        <h1>User Management</h1>
                        <p class="subtitle">View, search, edit and delete user and admin accounts.</p>
                    </div>
                    <button class="btn" type="button" id="openAdd">+ Add Account</button>
                </div>

                <div class="panel">
                    <div id="message" class="message" hidden></div>

                    <form id="searchForm" class="search-form">
                        <input id="search" name="search" placeholder="Search by name or email...">
                        <button class="btn" type="submit">Search</button>
                        <a class="btn" href="users.php">Reset</a>
                    </form>

                    <p id="accountCount" class="subtitle" aria-live="polite"></p>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type / ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Created at</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersBody">
                                <tr>
                                    <td colspan="5" class="empty">Loading accounts...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="account-pages">
                        <span id="pageLabel"></span>
                        <div>
                            <button class="btn" id="previousPage" type="button">Previous</button>
                            <button class="btn" id="nextPage" type="button">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit User</h2>

            <form method="POST" action="api/admin_users.php">
                <input type="hidden" name="csrf_token" id="editCsrf">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="account_id" id="editUserId">
                <input type="hidden" name="account_type" id="editAccountType">

                <label for="editName">Name</label>
                <input type="text" name="name" id="editName" maxlength="50" required>

                <label for="editEmail">Email</label>
                <input type="email" name="email" id="editEmail" maxlength="120" required>

                <div class="modal-buttons">
                    <button type="button" class="btn" id="cancelEdit">Cancel</button>
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Add account</h2>

            <form id="addUserForm">
                <input type="hidden" name="csrf_token" id="addCsrf">

                <label for="accountType">Account type</label>
                <select name="account_type" id="accountType">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>

                <label for="addName" id="addNameLabel">Name</label>
                <input type="text" name="name" id="addName" maxlength="50" required>

                <label for="addEmail">Email</label>
                <input type="email" name="email" id="addEmail" maxlength="120" required>

                <div id="userPasswordFields">
                    <label for="addPassword">Initial password for user</label>
                    <input type="password" name="password" id="addPassword" minlength="8" autocomplete="new-password" required>

                    <div id="addPasswordRequirements" class="password-requirements">
                        <p>Password must contain:</p>
                        <ul>
                            <li id="addRuleLength">At least 8 characters</li>
                            <li id="addRuleSpecial">At least 1 special character (e.g., ! @ # $)</li>
                            <li id="addRuleUppercase">At least 1 uppercase letter (A–Z)</li>
                        </ul>
                    </div>
                </div>

                <p id="adminPasswordNote" class="muted" hidden>
                    A temporary password will be generated automatically. The new admin will be required to change it after their first sign-in.
                </p>

                <div id="addMessage" class="message" hidden></div>

                <div id="temporaryPasswordPanel" class="message" hidden>
                    <strong>Copy this temporary admin password now:</strong>
                    <div style="display:flex;gap:8px;margin-top:8px">
                        <input id="temporaryPassword" readonly>
                        <button type="button" id="copyTemporaryPassword">Copy</button>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn" id="cancelAdd">Cancel</button>
                    <button type="submit" class="btn">Create account</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/users.js?v=admin-account-view-3" defer></script>
    <script src="admin_logout.js"></script>
</body>
</html>
