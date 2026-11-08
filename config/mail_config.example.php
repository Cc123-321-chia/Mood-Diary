<?php
// Copy to mail_config.local.php. Use a NEW app password; never commit/share it.
return [
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => 587,
    'smtp_encryption' => 'tls',
    'smtp_username'   => 'your-email@gmail.com',
    'smtp_password'   => 'your-new-app-password',
    'from_email'      => 'your-email@gmail.com',
];
