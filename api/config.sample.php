<?php
/**
 * ViscosiGuide configuration TEMPLATE.
 *
 * Copy this file to `api/config.php` on the server and fill in real values.
 * `api/config.php` is gitignored and MUST NEVER be committed — it holds secrets.
 *
 * On HostPoint: create a MariaDB database + user in the control panel, then paste
 * those credentials below. Create an e-mail mailbox for the "from" address and use
 * HostPoint's SMTP server (asmtp.mail.hostpoint.ch, port 587, STARTTLS).
 */

return [
    // 'production' enables Secure cookies + real SMTP + HSTS.
    // 'dev' relaxes Secure-cookie for http://localhost and logs mail to a file.
    'env' => 'production',

    'app' => [
        // Public base URL of the site (no trailing slash). Used in e-mail links.
        'base_url' => 'https://viscosiguide.example.ch',
        'name'     => 'ViscosiGuide',
    ],

    'db' => [
        // 'mysql' for HostPoint/MariaDB, 'sqlite' for local development.
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'CHANGE_ME_dbname',
        'user'     => 'CHANGE_ME_dbuser',
        'password' => 'CHANGE_ME_dbpassword',
        // Only used when driver === 'sqlite':
        'sqlite_path' => __DIR__ . '/var/viscosiguide.sqlite',
    ],

    'mail' => [
        'from_email' => 'noreply@viscosiguide.example.ch',
        'from_name'  => 'ViscosiGuide',
        'smtp' => [
            'host'       => 'asmtp.mail.hostpoint.ch',
            'port'       => 587,
            'encryption' => 'tls',           // 'tls' (STARTTLS) or 'ssl'
            'username'   => 'noreply@viscosiguide.example.ch',
            'password'   => 'CHANGE_ME_mailboxpassword',
        ],
    ],

    'security' => [
        // Comma-free list of extra allowed CORS origins is not used (same-origin app).
        // Session idle timeout in seconds (default 12h).
        'session_idle_timeout' => 12 * 60 * 60,
    ],
];
