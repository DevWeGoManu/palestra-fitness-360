<?php

$config = require __DIR__ . '/../config/config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

function app_origin(string $url): ?string
{
    $parts = parse_url($url);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return $parts['scheme'] . '://' . $parts['host'] . $port;
}

$allowedOrigins = array_filter(array_unique(array_merge(
    $config['allowed_origins'] ?? [],
    [app_origin($config['app_url'] ?? ''), app_origin($config['api_url'] ?? '')]
)));

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');

$cookiePath = parse_url($config['app_url'] ?? '', PHP_URL_PATH) ?: '/';
$cookiePath = rtrim($cookiePath, '/') ?: '/';

session_set_cookie_params([
    'path' => $cookiePath,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Validation.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Mailer.php';

set_exception_handler(static function (Throwable $exception): void {
    log_event('php_exception', ['message' => $exception->getMessage()]);
    json_response(['error' => 'Errore interno del server'], 500);
});

secure_headers();
ensure_csrf_token();
enforce_session_timeout((int) ($config['session_ttl'] ?? 3600));
enforce_api_rate_limit();
enforce_csrf();
