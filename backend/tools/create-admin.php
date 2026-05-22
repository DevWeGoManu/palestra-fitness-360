<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Validation.php';

$fullName = clean_string($argv[1] ?? '', 120);
$email = trim(strtolower($argv[2] ?? ''));
$password = (string) ($argv[3] ?? '');

if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Uso: php tools/create-admin.php \"Nome Admin\" admin@example.com \"PasswordSicura123!\"\n");
    fwrite(STDERR, "La password deve avere almeno 12 caratteri.\n");
    exit(1);
}

$pdo = Database::connection();
$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);

echo "Admin creato con ID " . $pdo->lastInsertId() . PHP_EOL;
