<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_role(['admin', 'istruttore']);
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($auth['role'] === 'admin') {
        $stmt = $pdo->query(
            'SELECT id, full_name, email, role, status, email_verified_at, created_at FROM users ORDER BY full_name ASC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, role, status, email_verified_at, created_at
             FROM users
             WHERE role <> ?
             ORDER BY full_name ASC'
        );
        $stmt->execute(['admin']);
    }
    json_response(['users' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json();
    $fullName = clean_string($data['full_name'] ?? '', 120);
    $email = trim(strtolower($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $role = clean_string($data['role'] ?? 'atleta', 20);

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        json_response(['error' => 'Nome, email valida e password di almeno 8 caratteri sono obbligatori'], 422);
    }
    if (!valid_role($role)) {
        json_response(['error' => 'Ruolo non valido'], 422);
    }
    if ($auth['role'] !== 'admin' && $role !== 'atleta') {
        json_response(['error' => 'Istruttore puo creare solo atleti'], 403);
    }

    try {
        $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, ?, NOW())'
    );
        $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, 'active']);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            json_response(['error' => 'Email gia registrata'], 409);
        }
        throw $exception;
    }

    $newId = (int) $pdo->lastInsertId();
    log_event('user_created', ['target_user_id' => $newId, 'role' => $role]);
    json_response(['id' => $newId], 201);
}

json_response(['error' => 'Metodo non consentito'], 405);
