# Combine Report — Team Leader v2 + Vivian Diary Module

## A. What the team leader added in v2

Compared with the earlier base ZIP, v2 adds a substantial authentication/admin layer:

- Login, registration and logout pages/APIs.
- `config/auth.php` with session security, `requireUser()`, password rules and session invalidation after password changes.
- Password reset flow, PHPMailer, reset database migrations and setup notes.
- Dashboard now redirects to Login when `get_diaries.php` returns HTTP 401.
- `get_diaries.php` and `update_profile.php` now use the authenticated session instead of hard-coded `user_id = 1`.
- Database errors in `config/db.php` are no longer returned verbatim.
- Initial Admin/User Management and Security Logs pages were added.

### v2 items that appear incomplete and should be checked by the team

- Admin APIs currently do not show a real **admin authorization guard**; `session_start()` alone does not prove the user is an administrator.
- `admin/users.html` references `../js/users.js`, but that file is not present in the ZIP.
- `admin/view_user.html` references `../js/view_user.js`, but that file is not present.
- `admin/security_logs.html` references `security_logs_api.php`, which is not present.
- `admin/api/view_user.php` appears to use a DB include path that should be reviewed (`../config/db.php` from `admin/api/`).
- The original ZIP contained an SMTP App Password in source code. It must be revoked and moved to local/env configuration.

These are team-level observations; they are not part of Vivian's assigned Diary CRUD functions.

---

## B. Vivian's assigned module

Vivian's scope:

- Create Diary
- Edit Diary
- Delete Diary
- Upload Photos / Videos

The module is now connected to the team leader's **real login/session environment**, not the old development `user_id = 1` mode.

### New files added

- `js/diary-crud.js` — Diary editor, form validation, create/edit/delete requests, media selection and preview.
- `css/diary-editor.css` — Diary editor styling using the dashboard theme variables.
- `api/_diary_common.php` — shared secure helper functions for diary APIs.
- `api/save_diary.php` — Create + Edit with server validation and ownership checks.
- `api/delete_diary.php` — Delete with ownership check and physical media cleanup.
- `api/upload_media.php` — secure image/video upload.
- `uploads/media/.htaccess` — prevents executable script types inside the upload area.

### Existing team files modified for integration

- `index.html`
  - loads `diary-editor.css`
  - loads `diary-crud.js` before `dashboard.js`

- `js/dashboard.js`
  - receives CSRF token from backend
  - `Write Diary` opens the editor
  - detail page gets Edit/Delete actions
  - actual uploaded videos can play
  - reloads database data after CRUD operations
  - profile update sends the same CSRF token
  - team leader's new Logout + session redirect logic is preserved

- `api/get_diaries.php`
  - still uses `requireUser()` from the team leader
  - returns a CSRF token
  - returns `media_id` so individual saved media can be securely removed during Edit
  - hides detailed server errors from the browser

- `config/auth.php`
  - keeps the leader's session design
  - adds reusable `csrfToken()` and `requireCsrf()` functions

- `api/update_profile.php`
  - now checks the same CSRF token
  - detailed exceptions are logged instead of exposed to users

### Supporting environment/security changes

- `config/db.php` now supports an optional local-only `db.local.php`, so Vivian can use port 3307 without forcing the team leader to change their port.
- `config/mail_config.php` no longer contains a shared SMTP password.
- `.gitignore` excludes local DB/mail secrets and uploaded user files.

---

## C. Database operations used by Vivian's module

### Create

`INSERT INTO diaries (...)`

The authenticated user ID comes from `requireUser()`. It is never accepted from the browser.

### Edit

The backend first verifies:

`WHERE diary_id = ? AND user_id = ?`

Only the owner can update the record.

### Delete

Deletion also requires both `diary_id` and current authenticated `user_id`. Associated media DB rows are removed through the diary relation, while uploaded files are cleaned from disk by PHP.

### Media

- maximum 9 per diary
- image max 8 MB each
- video max 40 MB each
- selected batch max 80 MB
- MIME type checked by PHP `finfo`, not only file extension
- random server filenames
- only approved image/video MIME types are accepted
