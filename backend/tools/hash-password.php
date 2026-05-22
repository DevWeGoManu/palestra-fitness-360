<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';
if (strlen($password) < 8) {
    fwrite(STDERR, "Uso: php tools/hash-password.php 'PasswordSicura123!'\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
