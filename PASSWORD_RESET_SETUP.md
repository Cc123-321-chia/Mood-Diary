# Password recovery

Mood Diary follows Oriental Kopi's email-code flow:
1. Enter the registered email on forgot_password.html.
2. Receive a six-digit code, valid for 30 minutes.
3. Enter email, code, new password and confirmation on reset_password.html.
4. Log in with the new password. A password-change notification is emailed.

Codes are stored as hashes, used once, and replaced when a new code is sent successfully. Resends are limited to once per minute per account. Five incorrect code attempts lock that code. Password changes revoke existing sessions.

## SMTP setup

The app uses a local copy of Oriental Kopi's PHPMailer library and reads its existing private SMTP configuration at ../oriental-kopi-booking/includes/mail_config.php. Credentials are not copied into this project, and Oriental Kopi is not modified. Emails use the configured sender address with the display name Mood Diary.

For independent deployment, set MOOD_MAIL_CONFIG to an absolute path to a private PHP file returning smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption (tls or ssl), and from_email. Keep credentials out of version control. SMTP must authorize the configured sender address. Remove the cross-project dependency by providing this file before moving the app.

New installations: apply database/password_resets.sql. Existing installations: apply database/password_reset_codes_migration.sql.

admin@mooddiary.local is not a real mailbox. Register a real email address to receive a code. SMTP connection checks do not prove inbox delivery; verify delivery to a real registered mailbox.
