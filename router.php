<?php
// Router for PHP's built-in server: php -S 127.0.0.1:8000 router.php
// Without it, the built-in server serves every file in this folder (.git/, schema.sql, lib/ ...).
// Only the two entry points and the assets folder are public.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$public = ['/', '/index.php', '/api.php', '/assets/style.css', '/assets/app.js'];
if (in_array($path, $public, true)) {
    return false; // let the built-in server handle it normally
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
