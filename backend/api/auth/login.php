<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
$email = trim(strtolower($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (!rate_limit('login', client_ip() . '|' . $email, 8, 300)) {
    log_event('login_rate_limited', ['email' => $email]);
    json_response(['error' => 'Troppi tentativi. Riprova tra qualche minuto'], 429);
}

if ($email === '' || $password === '') {
    json_response(['error' => 'Email e password sono obbligatorie'], 422);
}

$stmt = Database::connection()->prepare(
    'SELECT id, full_name, email, password_hash, role, status, email_verified_at, created_at FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_event('login_failed', ['email' => $email]);
    json_response(['error' => 'Credenziali non valide'], 401);
}

if ($user['email_verified_at'] === null) {
    log_event('login_unverified', ['email' => $email]);
    json_response(['error' => 'Verifica la tua email prima di accedere'], 403);
}
if ($user['status'] === 'pending') {
    log_event('login_pending', ['email' => $email]);
    json_response(['error' => 'Account in attesa di approvazione'], 403);
}
if ($user['status'] === 'disabled') {
    log_event('login_disabled', ['email' => $email]);
    json_response(['error' => 'Account disabilitato'], 403);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['last_activity'] = time();
$_SESSION['regenerated_at'] = time();
ensure_csrf_token();
unset($user['password_hash']);

log_event('login_success', ['email' => $email]);
json_response(['user' => $user, 'csrf_token' => csrf_token()]);
