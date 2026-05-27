<?php

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    $config = app_config();
    $from = $config['mail_from'] ?? 'noreply@localhost';
    $fromName = $config['mail_from_name'] ?? 'AthleoDesk';
    $safeFrom = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'noreply@localhost';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'From: ' . $fromName . ' <' . $safeFrom . '>',
        'Reply-To: ' . $safeFrom,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = false;
    $error = null;
    if (function_exists('mail')) {
        $parameters = filter_var($safeFrom, FILTER_VALIDATE_EMAIL) ? '-f' . $safeFrom : '';
        $sent = $parameters !== ''
            ? @mail($to, $encodedSubject, $body, implode("\r\n", $headers), $parameters)
            : @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) {
            $error = error_get_last()['message'] ?? null;
        }
    } else {
        $error = 'Funzione mail() non disponibile';
    }

    $logDir = __DIR__ . '/../storage/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        file_put_contents($logDir . '/mail.log', json_encode([
            'time' => gmdate('c'),
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'mail_sent' => $sent,
            'from' => $safeFrom,
            'error' => $error,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    if (!$sent) {
        log_event('mail_failed', ['to' => $to, 'subject' => $subject, 'from' => $safeFrom, 'error' => $error]);
    }

    return $sent;
}

function send_app_mail_with_attachment(string $to, string $subject, string $body, ?array $attachment = null): bool
{
    if ($attachment === null) {
        return send_app_mail($to, $subject, $body);
    }

    $config = app_config();
    $from = $config['mail_from'] ?? 'noreply@localhost';
    $fromName = $config['mail_from_name'] ?? 'AthleoDesk';
    $safeFrom = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'noreply@localhost';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary = 'gym_ticket_' . bin2hex(random_bytes(16));
    $filename = preg_replace('/[^a-z0-9._-]/i', '_', $attachment['name'] ?? 'screenshot') ?: 'screenshot';
    $mime = $attachment['type'] ?? 'application/octet-stream';
    $content = chunk_split(base64_encode((string) ($attachment['content'] ?? '')));

    $headers = [
        'From: ' . $fromName . ' <' . $safeFrom . '>',
        'Reply-To: ' . $safeFrom,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: $mime; name=\"$filename\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
    $message .= $content . "\r\n";
    $message .= "--$boundary--";

    $sent = false;
    $error = null;
    if (function_exists('mail')) {
        $parameters = filter_var($safeFrom, FILTER_VALIDATE_EMAIL) ? '-f' . $safeFrom : '';
        $sent = $parameters !== ''
            ? @mail($to, $encodedSubject, $message, implode("\r\n", $headers), $parameters)
            : @mail($to, $encodedSubject, $message, implode("\r\n", $headers));
        if (!$sent) {
            $error = error_get_last()['message'] ?? null;
        }
    } else {
        $error = 'Funzione mail() non disponibile';
    }

    $logDir = __DIR__ . '/../storage/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        file_put_contents($logDir . '/mail.log', json_encode([
            'time' => gmdate('c'),
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'attachment' => $filename,
            'mail_sent' => $sent,
            'from' => $safeFrom,
            'error' => $error,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    if (!$sent) {
        log_event('mail_failed', ['to' => $to, 'subject' => $subject, 'from' => $safeFrom, 'error' => $error]);
    }

    return $sent;
}

function send_verification_email(string $email, string $fullName, string $token): void
{
    $config = app_config();
    $link = rtrim($config['app_url'], '/') . '/#/verify-email?token=' . urlencode($token);
    send_app_mail(
        $email,
        'Verifica email - AthleoDesk',
        "Ciao $fullName,\n\nverifica la tua email aprendo questo link:\n$link\n\nIl link scade tra 24 ore."
    );
}

function send_password_reset_email(string $email, string $fullName, string $token): void
{
    $config = app_config();
    $link = rtrim($config['app_url'], '/') . '/#/reset-password?token=' . urlencode($token);
    send_app_mail(
        $email,
        'Reset password - AthleoDesk',
        "Ciao $fullName,\n\npuoi impostare una nuova password aprendo questo link:\n$link\n\nIl link scade tra 60 minuti."
    );
}

function notify_admin_registration(string $fullName, string $email): void
{
    $config = app_config();
    $adminEmail = trim($config['admin_notify_email'] ?? '');
    if ($adminEmail === '') {
        return;
    }

    send_app_mail(
        $adminEmail,
        'Nuovo utente registrato - AthleoDesk',
        "Nuovo utente registrato:\n\nNome: $fullName\nEmail: $email\n\nAccedi al pannello utenti per approvarlo."
    );
}
