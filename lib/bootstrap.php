<?php
// Shared setup for index.php and api.php:
// error settings, database connection, session, and small security helpers.

// Do not show errors to users. Write them to the server log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Stop the request with a status code and a short message.
// The API gets JSON, the web page gets plain text.
function fail($status, $message)
{
    http_response_code($status);
    if (basename($_SERVER['SCRIPT_NAME']) === 'api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit;
}

// Any uncaught error (for example the database is down): log it, show a generic message.
set_exception_handler(function ($e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    fail(500, 'Something went wrong. Please try again later.');
});

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Credentials must come from environment variables. No fallback to root.
    $user = getenv('LAB_DB_USER');
    $pass = getenv('LAB_DB_PASSWORD');
    if (!$user || $pass === false) {
        throw new Exception('LAB_DB_USER and LAB_DB_PASSWORD are not set.');
    }
    $host = getenv('LAB_DB_HOST') ?: '127.0.0.1';
    $port = getenv('LAB_DB_PORT') ?: '3306';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=notes_lab;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

// Safer session cookie: JavaScript cannot read it and it is not sent on cross-site POSTs.
// cookie_secure is turned on automatically when the site runs on HTTPS.
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Escape text before printing it in HTML (prevents XSS).
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// One random CSRF token per session.
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid($token)
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// If the browser sends an Origin header, it must be this same site.
function same_origin()
{
    if (empty($_SERVER['HTTP_ORIGIN'])) {
        return true; // no Origin header (e.g. curl); the CSRF token still protects POSTs
    }
    $parts = parse_url($_SERVER['HTTP_ORIGIN']);
    $origin = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    return $origin === ($_SERVER['HTTP_HOST'] ?? '');
}

function log_in($user)
{
    session_regenerate_id(true); // new session ID after login (prevents session fixation)
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['csrf_token']); // new token for the logged-in session
}

function log_out()
{
    $_SESSION = [];
    setcookie(session_name(), '', time() - 3600, '/');
    session_destroy();
}
