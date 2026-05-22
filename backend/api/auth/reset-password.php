<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
$token = clean_string($data['token'] ?? '', 160);
$password = (string) ($data['password'] ?? '');
$confirmPassword = (string) ($data['password_confirm'] ?? '');

if ($token === '' || strlen($password) < 8 || $password !== $confirmPassword) {
    json_response(['error' => 'Token o password non validi'], 422);
}

$pdo = Database::connection();
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare(
    'SELECT prt.id, prt.user_id, prt.expires_at, prt.used_at
     FROM password_reset_tokens prt
     WHERE prt.token_hash = ?
     LIMIT 1'
);
$stmt->execute([$tokenHash]);
$row = $stmt->fetch();

if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
    json_response(['error' => 'Token non valido o scaduto'], 422);
}

$pdo->beginTransaction();
$updateUser = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$updateUser->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
$markToken = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
$markToken->execute([$row['id']]);
$invalidate = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
$invalidate->execute([$row['user_id']]);
$pdo->commit();

log_event('password_reset_completed', ['target_user_id' => (int) $row['user_id']]);
json_response(['ok' => true, 'message' => 'Password aggiornata']);
