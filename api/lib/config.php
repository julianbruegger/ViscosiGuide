<?php
declare(strict_types=1);

/**
 * Loads application configuration.
 *
 * Priority:
 *   1. api/config.php (real secrets on the server — gitignored)
 *   2. Environment variables (handy for CI / local smoke tests)
 *
 * Returns an immutable config array (cached across calls in one request).
 */
function vg_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $file = __DIR__ . '/../config.php';
    if (is_file($file)) {
        /** @var array $config */
        $config = require $file;
        return $config;
    }

    // Fallback: build config from environment variables.
    $env = getenv('APP_ENV') ?: 'dev';
    $config = [
        'env' => $env,
        'app' => [
            'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8000',
            'name'     => getenv('APP_NAME') ?: 'ViscosiGuide',
        ],
        'db' => [
            'driver'      => getenv('DB_DRIVER') ?: 'sqlite',
            'host'        => getenv('DB_HOST') ?: 'localhost',
            'port'        => (int) (getenv('DB_PORT') ?: 3306),
            'name'        => getenv('DB_NAME') ?: 'viscosiguide',
            'user'        => getenv('DB_USER') ?: 'root',
            'password'    => getenv('DB_PASSWORD') ?: '',
            'sqlite_path' => getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/../var/viscosiguide.sqlite'),
        ],
        'mail' => [
            'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'noreply@localhost',
            'from_name'  => getenv('MAIL_FROM_NAME') ?: 'ViscosiGuide',
            'smtp' => [
                'host'       => getenv('SMTP_HOST') ?: '',
                'port'       => (int) (getenv('SMTP_PORT') ?: 587),
                'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
                'username'   => getenv('SMTP_USERNAME') ?: '',
                'password'   => getenv('SMTP_PASSWORD') ?: '',
            ],
        ],
        'security' => [
            'session_idle_timeout' => (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 43200),
        ],
    ];

    return $config;
}

/** True when running in the local/dev environment (relaxed cookie + file-based mail). */
function vg_is_dev(): bool
{
    return (vg_config()['env'] ?? 'production') !== 'production';
}
