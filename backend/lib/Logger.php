<?php

function log_event(string $type, array $context = []): void
{
    $entry = [
        'time' => gmdate('c'),
        'type' => $type,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? null,
        'context' => $context,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $logDir = __DIR__ . '/../storage/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
        return;
    }

    error_log($line);
}
