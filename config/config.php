<?php
define('SITE_NAME', 'AfroStyle');
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'afrostyle78.com'));
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_DIR', __DIR__ . '/../uploads/products/');
define('UPLOADS_URL', SITE_URL . '/uploads/products/');
define('CURRENCY', '€');
define('SESSION_NAME', 'afrostyle_session');
define('TURNSTILE_SITE_KEY',   '0x4AAAAAADfnNvPrt7np4W0i');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAADfnNpKrPL3Lim76NDlBfA0nCmA');

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
