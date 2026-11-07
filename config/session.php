<?php
// Start the PHP session with safer cookie settings.
//  - httponly: JavaScript cannot read the session cookie (protects against XSS theft)
//  - samesite Lax: the cookie is not sent on cross-site POST requests (CSRF protection)
//  - secure: cookie only travels over HTTPS when the site is served through
//    HTTPS (Cloudflare tells us via the X-Forwarded-Proto header)
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
