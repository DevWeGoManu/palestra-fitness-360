<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/Database.php';

$pdo = Database::connection();
$stmt = $pdo->prepare(
    "DELETE FROM users WHERE email IN ('admin@palestra.local', 'coach@palestra.local', 'atleta@palestra.local')"
);
$stmt->execute();

echo "Utenti demo rimossi: " . $stmt->rowCount() . PHP_EOL;
