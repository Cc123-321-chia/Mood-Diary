<?php
// Copy to mail_config.local.php on your own machine.
// Never commit or share real SMTP credentials.
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => 'your-email@example.com',
    'smtp_password' => 'your-app-password',
    'from_email' => 'your-email@example.com',
];
