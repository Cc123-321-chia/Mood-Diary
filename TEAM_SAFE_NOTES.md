# Team-safe combined package

This package is based on the team leader's `mood_diary 2` version and includes the Diary CRUD/media module.

## Important
- No `config/db.local.php` is included. The shared `config/db.php` defaults to MySQL port **3306**.
- If your machine uses another port, copy `config/db.local.example.php` to `config/db.local.php` and edit it locally.
- Do not share `db.local.php`.
- The destructive local reset SQL used for Vivian's clean XAMPP test is intentionally NOT included.
- Test-uploaded media files are intentionally NOT included.
- Real SMTP credentials are intentionally NOT included. Use `mail_config.local.php` locally.

## Diary module added
- Create Diary
- Edit Diary
- Delete Diary
- Photo/video upload
- CSRF protection
- Session authentication
- Object ownership authorization
- Prepared statements
- Server-side media validation
