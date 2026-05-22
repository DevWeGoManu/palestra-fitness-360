<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
if (trim((string) ($data['website'] ?? '')) !== '') {
    log_event('register_honeypot');
    json_response(['ok' => true], 201);
}

$firstName = clean_string($data['first_name'] ?? '', 60);
$lastName = clean_string($data['last_name'] ?? '', 60);
$email = trim(strtolower($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirmPassword = (string) ($data['password_confirm'] ?? '');
$accepted = (bool) ($data['accepted_terms'] ?? false);

if (!rate_limit('register', client_ip() . '|' . $email, 5, 600)) {
    log_event('register_rate_limited', ['email' => $email]);
    json_response(['error' => 'Troppe registrazioni. Riprova piu tardi'], 429);
}

if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Nome, cognome ed email valida sono obbligatori'], 422);
}
if (strlen($password) < 8 || $password !== $confirmPassword) {
    json_response(['error' => 'Password non valida o conferma non corrispondente'], 422);
}
if (!$accepted) {
    json_response(['error' => 'Devi accettare privacy e termini'], 422);
}

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $firstName . ' ' . $lastName,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        'atleta',
        'pending',
    ]);
    $userId = (int) $pdo->lastInsertId();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $insertToken = $pdo->prepare(
        'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    );
    $insertToken->execute([$userId, $tokenHash]);
    $pdo->commit();
} catch (PDOException $exception) {
    $pdo->rollBack();
    if ($exception->getCode() === '23000') {
        json_response(['error' => 'Email gia registrata'], 409);
    }
    throw $exception;
}

send_verification_email($email, $firstName . ' ' . $lastName, $token);
notify_admin_registration($firstName . ' ' . $lastName, $email);
log_event('user_registered', ['target_user_id' => $userId, 'email' => $email]);

json_response([
    'ok' => true,
    'message' => 'Registrazione completata. Controlla la tua email per verificare l account.',
], 201);
