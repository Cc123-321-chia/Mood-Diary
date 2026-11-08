# Mood Diary v2 + Diary CRUD — Run Guide

This build starts from the team leader's **mood_diary 2.zip** and keeps the new Login / Logout / Session / Password Reset / Admin files. Vivian's Diary Management module is then merged on top.

## 1. Put the project in XAMPP

Place the folder at:

`C:\xampp\htdocs\mood_diary`

Start **Apache** and **MySQL/MariaDB** in XAMPP.

## 2. Database connection

Shared defaults in `config/db.php` use port **3306**.

For Vivian's machine, `config/db.local.php` is already set to **3307**. This file is local-only and is ignored by Git.

Team members using another port should copy:

`config/db.local.example.php` -> `config/db.local.php`

and change only their local values.

## 3. Database schema

This v2 ZIP did not contain the full base schema. The existing `mood_diary` database must already contain the team's tables, especially:

- `users`
- `user_settings`
- `themes`
- `diaries`
- `moods`
- `weathers`
- `media`
- `security_logs`
- password-reset tables used by the team's auth module

The diary module follows the agreed ERD: one diary per user/date, `mood_id` + `weather_id`, and media `sort_order` 0–8 (maximum 9 files).

For a **fresh install**, import [`database/mood_diary_full.sql`](database/mood_diary_full.sql) in phpMyAdmin. It creates the app's tables and seeds the theme, mood, and weather choices. It does not create sample accounts or drop existing data.

For an **existing database**, do not use that full-install file as a schema updater: `CREATE TABLE IF NOT EXISTS` will not alter tables already present. Apply only the required migrations, such as `database/admin_temporary_password.sql` if the `admins.must_change_password` column is missing. The standalone `database/security_logs.sql` is available if the existing database lacks the audit table.

## 4. Open the system

Open:

`http://localhost/mood_diary/login.html`

Log in with a registered account, then the dashboard will load `index.html`.

The admin area is at `http://localhost/mood_diary/admin/login.html`. After an administrator signs in, `admin/index.php` shows user and diary totals, today's security activity, and open critical events. Create the initial admin with a PHP `password_hash()` value; then admins can be managed from **Users**. For example, after generating a password hash in PHP, insert it with:

```sql
INSERT INTO admins (name, email, password_hash, must_change_password)
VALUES ('Admin', 'admin@example.com', 'PASTE_PHP_PASSWORD_HASH_HERE', 1);
```

The full-install SQL already includes the temporary-password flag. On an existing database, run `database/admin_temporary_password.sql` once if that column is missing; existing admins will then be prompted once to choose a new password. Newly created admins must change their temporary password after their first sign-in.

The full-install SQL also includes security-log resolutions. On an existing database without that table, run `admin/resolve_security_logs.sql` once to enable the dashboard's **Resolve** action.

## 5. Test Vivian's functions

1. **Create Diary** — click `Write Diary`, fill Date/Time/Mood/Weather/Content, Save.
2. **Edit Diary** — open a diary card, click `Edit Diary`, change data, Save.
3. **Delete Diary** — open a diary card, click `Delete Diary`, confirm.
4. **Upload Photos / Videos** — add supported media in the editor and save.
5. Verify `diaries` and `media` in phpMyAdmin.
6. Log in as another user and verify they cannot edit/delete the first user's diary.

## 6. Password-reset email configuration

The SMTP password has deliberately been removed from shared source code.

Copy:

`config/mail_config.example.php` -> `config/mail_config.local.php`

and enter a **new** app password locally. Never commit or send `mail_config.local.php`.

The previously shared app password should be revoked because it appeared in source/screenshots.
