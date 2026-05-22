<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/Database.php';

$email = trim(strtolower($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Uso: php tools/change-admin-password.php admin@example.com \"NuovaPasswordSicura123!\"\n");
    fwrite(STDERR, "La password deve avere almeno 12 caratteri.\n");
    exit(1);
}

$pdo = Database::connection();
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ? AND role = ?');
$stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email, 'admin']);

if ($stmt->rowCount() < 1) {
    fwrite(STDERR, "Nessun admin trovato con email: $email\n");
    exit(1);
}

echo "Password admin aggiornata per $email" . PHP_EOL;
