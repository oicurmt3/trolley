<?php
/**
 * Local configuration for WA Trolley Report — copy to config.local.php on the server.
 * DO NOT commit config.local.php (gitignored and blocked by .htaccess).
 *
 * Document every key below before deploying.
 */
declare(strict_types=1);

return [
    /**
     * admin_password_hash (required for admin.php)
     * Bcrypt hash from your existing live admin.php:
     *   password_hash('your-password', PASSWORD_BCRYPT)
     */
    'admin_password_hash' => '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FROM_LIVE_SERVER',

    /**
     * cors_allowed_origins (optional)
     * Origins allowed to call JSON APIs with CORS (log_trolley.php, api_logs.php, etc.).
     * Same-origin browser requests do not send Origin and are unaffected.
     * Default when omitted: https://sandgroper.net and https://www.sandgroper.net
     */
    'cors_allowed_origins' => [
        'https://sandgroper.net',
        'https://www.sandgroper.net',
    ],

    /**
     * site_public_base (optional)
     * Public HTTPS URL of this install without trailing slash.
     * Used by CLI token export (portal link map) — not shown to reporters.
     */
    'site_public_base' => 'https://sandgroper.net/trolley',
];