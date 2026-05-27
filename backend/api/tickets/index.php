<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$user = require_user();
$message = clean_text($_POST['message'] ?? '', 3000);

if ($message === '' || strlen($message) < 10) {
    json_response(['error' => 'Descrivi il problema con almeno 10 caratteri'], 422);
}

if (!rate_limit('ticket', client_ip() . '|' . $user['id'], 5, 600)) {
    log_event('ticket_rate_limited', ['user_id' => $user['id']]);
    json_response(['error' => 'Hai inviato troppe segnalazioni. Riprova tra qualche minuto'], 429);
}

$attachment = null;
if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Immagine non caricata correttamente'], 422);
    }

    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        json_response(['error' => 'L immagine deve essere inferiore a 4MB'], 422);
    }

    $tmpName = $file['tmp_name'] ?? '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $tmpName !== '' ? ($finfo->file($tmpName) ?: '') : '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        json_response(['error' => 'Formato immagine non supportato. Usa JPG, PNG o WebP'], 422);
    }

    $attachment = [
        'name' => $file['name'] ?? 'screenshot',
        'type' => $mime,
        'content' => file_get_contents($tmpName),
    ];
}

$subject = 'Ticket malfunzionamento - AthleoDesk';
$body = "Nuovo ticket ricevuto:\n\n";
$body .= "Utente: {$user['full_name']}\n";
$body .= "Email: {$user['email']}\n";
$body .= "Ruolo: {$user['role']}\n";
$body .= "Data: " . date('Y-m-d H:i:s') . "\n";
$body .= "IP: " . client_ip() . "\n\n";
$body .= "Messaggio:\n$message\n";

$sent = send_app_mail_with_attachment('emanuele.masciari@hotmail.it', $subject, $body, $attachment);
log_event('ticket_created', ['user_id' => $user['id'], 'mail_sent' => $sent, 'has_image' => $attachment !== null]);

json_response([
    'message' => $sent
        ? 'Ticket inviato correttamente'
        : 'Ticket registrato, ma l invio email non e disponibile in questo ambiente',
    'mail_sent' => $sent,
]);
