<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
$email = trim(strtolower($data['email'] ?? ''));

if (!rate_limit('password_reset_request', client_ip() . '|' . $email, 5, 600)) {
    log_event('password_reset_rate_limited', ['email' => $email]);
    json_response(['message' => 'Se l email esiste, riceverai un link'], 200);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $pdo = Database::connection();
    $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $insert = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE), ?)'
        );
        $insert->execute([$user['id'], $tokenHash, client_ip()]);
        send_password_reset_email($user['email'], $user['full_name'], $token);
        log_event('password_reset_requested', ['target_user_id' => (int) $user['id'], 'email' => $email]);
    } else {
        log_event('password_reset_requested_unknown', ['email' => $email]);
    }
}

json_response(['message' => 'Se l email esiste, riceverai un link']);
