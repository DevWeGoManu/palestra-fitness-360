<?php
require_once __DIR__ . '/../bootstrap.php';

$token = clean_string($_GET['token'] ?? input_json()['token'] ?? '', 160);
if ($token === '') {
    json_response(['error' => 'Token mancante'], 422);
}

$pdo = Database::connection();
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare(
    'SELECT evt.id, evt.user_id, evt.expires_at, evt.used_at, u.email
     FROM email_verification_tokens evt
     JOIN users u ON u.id = evt.user_id
     WHERE evt.token_hash = ?
     LIMIT 1'
);
$stmt->execute([$tokenHash]);
$row = $stmt->fetch();

if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
    json_response(['error' => 'Token non valido o scaduto'], 422);
}

$pdo->beginTransaction();
$updateUser = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?');
$updateUser->execute([$row['user_id']]);
$updateToken = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?');
$updateToken->execute([$row['id']]);
$pdo->commit();

log_event('email_verified', ['target_user_id' => (int) $row['user_id'], 'email' => $row['email']]);
json_response(['ok' => true, 'message' => 'Email verificata. Attendi approvazione account se non ancora attivo.']);
