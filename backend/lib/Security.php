<?php

function secure_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function ensure_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token(): string
{
    return ensure_csrf_token();
}

function enforce_csrf(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $publicPosts = [
        '/api/auth/login.php',
        '/api/auth/register.php',
        '/api/auth/request-password-reset.php',
        '/api/auth/reset-password.php',
    ];
    foreach ($publicPosts as $publicPost) {
        if (str_ends_with($path, $publicPost)) {
            return;
        }
    }
    if (str_ends_with($path, '/api/auth/verify-email.php') && $method === 'POST') {
        return;
    }

    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($header === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $header)) {
        log_event('csrf_rejected');
        json_response(['error' => 'Token CSRF non valido'], 419);
    }
}

function enforce_session_timeout(int $ttlSeconds = 3600): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $last = (int) ($_SESSION['last_activity'] ?? $now);
    if (($now - $last) > $ttlSeconds) {
        log_event('session_timeout');
        $_SESSION = [];
        session_destroy();
        json_response(['error' => 'Sessione scaduta'], 401);
    }

    $_SESSION['last_activity'] = $now;

    $regeneratedAt = (int) ($_SESSION['regenerated_at'] ?? 0);
    if (($now - $regeneratedAt) > 900) {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = $now;
    }
}

function rate_limit(string $bucket, string $key, int $limit, int $windowSeconds): bool
{
    $safeBucket = preg_replace('/[^a-z0-9_-]/i', '_', $bucket) ?: 'default';
    $hash = hash('sha256', $key);
    $file = sys_get_temp_dir() . '/gym_rate_' . $safeBucket . '.json';
    $now = time();

    $handle = fopen($file, 'c+');
    if (!$handle) {
        return true;
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    foreach ($data as $storedKey => $timestamps) {
        $data[$storedKey] = array_values(array_filter($timestamps, static fn ($timestamp) => $timestamp > $now - $windowSeconds));
        if (!$data[$storedKey]) {
            unset($data[$storedKey]);
        }
    }

    $attempts = $data[$hash] ?? [];
    if (count($attempts) >= $limit) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $attempts[] = $now;
    $data[$hash] = $attempts;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function enforce_api_rate_limit(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (str_ends_with($path, '/api/auth/login.php')) {
        return;
    }

    $sessionUser = $_SESSION['user_id'] ?? 'guest';
    if (!rate_limit('api', client_ip() . '|' . $sessionUser, 180, 60)) {
        log_event('api_rate_limited');
        json_response(['error' => 'Troppe richieste. Riprova tra poco'], 429);
    }
}

function validate_upload_mime_placeholder(string $mime): bool
{
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    return in_array($mime, $allowed, true);
}
