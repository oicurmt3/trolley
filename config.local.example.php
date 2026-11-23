<?php
/**
 * Local secrets for WA Trolley Report — copy to config.local.php on the server.
 * DO NOT commit config.local.php (it is gitignored and blocked by .htaccess).
 *
 * Before uploading a new admin.php, copy your existing bcrypt hash from the
 * current server admin.php into config.local.php on the server.
 */
declare(strict_types=1);

return [
    // password_hash('your-password', PASSWORD_BCRYPT) from the live server
    'admin_password_hash' => '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FROM_LIVE_SERVER',
];