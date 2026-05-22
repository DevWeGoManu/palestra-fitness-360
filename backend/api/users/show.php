<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    json_response(['error' => 'ID utente non valido'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ((int) $auth['id'] !== $id && !can_manage($auth)) {
        json_response(['error' => 'Permesso negato'], 403);
    }

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, role, status, email_verified_at, created_at FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['error' => 'Utente non trovato'], 404);
    }

    $plans = $pdo->prepare(
        'SELECT id, name, created_at FROM workout_plans WHERE assigned_user_id = ? ORDER BY created_at DESC'
    );
    $plans->execute([$id]);

    $history = $pdo->prepare(
        'SELECT ws.id, ws.completed_at, wp.name AS workout_plan_name
         FROM workout_sessions ws
         JOIN workout_plans wp ON wp.id = ws.workout_plan_id
         WHERE ws.user_id = ?
         ORDER BY ws.completed_at DESC
         LIMIT 20'
    );
    $history->execute([$id]);

    json_response(['user' => $user, 'plans' => $plans->fetchAll(), 'sessions' => $history->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        json_response(['error' => 'Utente non trovato'], 404);
    }

    $isSelf = (int) $auth['id'] === $id;

    if (!$isSelf && !can_manage($auth)) {
        json_response(['error' => 'Permesso negato'], 403);
    }
    if (!$isSelf && $auth['role'] !== 'admin' && $target['role'] === 'admin') {
        json_response(['error' => 'Istruttore non puo modificare admin'], 403);
    }

    $data = input_json();
    $fullName = clean_string($data['full_name'] ?? '', 120);
    $email = trim(strtolower($data['email'] ?? ''));
    if ($auth['role'] !== 'admin') {
        $requestedRole = array_key_exists('role', $data) ? clean_string($data['role'], 20) : $target['role'];
        $requestedStatus = array_key_exists('status', $data) ? clean_string($data['status'], 20) : $target['status'];
        if ($requestedRole !== $target['role']) {
            json_response(['error' => 'Solo admin puo modificare il ruolo'], 403);
        }
        if ($requestedStatus !== $target['status']) {
            json_response(['error' => 'Solo admin puo modificare lo status'], 403);
        }
    }
    $role = $auth['role'] === 'admin' ? clean_string($data['role'] ?? $target['role'], 20) : $target['role'];
    $status = $auth['role'] === 'admin' ? clean_string($data['status'] ?? $target['status'], 20) : $target['status'];
    $password = (string) ($data['password'] ?? '');

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !valid_role($role) || !valid_status($status)) {
        json_response(['error' => 'Nome, email valida, ruolo e status validi sono obbligatori'], 422);
    }
    if ($auth['role'] !== 'admin' && !$isSelf && ($role === 'admin' || $target['role'] === 'admin')) {
        json_response(['error' => 'Solo admin puo gestire utenti admin'], 403);
    }
    if ($auth['role'] !== 'admin' && !$isSelf && ($target['role'] !== 'atleta' || $role !== 'atleta')) {
        json_response(['error' => 'Istruttore puo gestire solo atleti'], 403);
    }
    if ($isSelf && $status !== 'active') {
        json_response(['error' => 'Non puoi disabilitare il tuo utente'], 422);
    }
    if ($password !== '' && strlen($password) < 8) {
        json_response(['error' => 'La nuova password deve avere almeno 8 caratteri'], 422);
    }

    try {
        if ($password !== '') {
            $update = $pdo->prepare(
                'UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, password_hash = ? WHERE id = ?'
            );
            $update->execute([$fullName, $email, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $update = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?');
            $update->execute([$fullName, $email, $role, $status, $id]);
        }
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            json_response(['error' => 'Email gia registrata'], 409);
        }
        throw $exception;
    }

    log_event('user_updated', ['target_user_id' => $id, 'role' => $role, 'status' => $status]);
    json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($auth['role'] !== 'admin') {
        json_response(['error' => 'Solo admin puo eliminare utenti'], 403);
    }
    if ((int) $auth['id'] === $id) {
        json_response(['error' => 'Non puoi eliminare il tuo utente'], 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Utente non trovato'], 404);
    }

    $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $delete->execute([$id]);
    log_event('user_deleted', ['target_user_id' => $id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Metodo non consentito'], 405);
