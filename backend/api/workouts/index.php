<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (can_manage($auth)) {
        $stmt = $pdo->query(
            'SELECT wp.id, wp.name, wp.assigned_user_id, u.full_name AS assigned_user_name, wp.created_at
             FROM workout_plans wp
             LEFT JOIN users u ON u.id = wp.assigned_user_id
             ORDER BY wp.created_at DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT wp.id, wp.name, wp.assigned_user_id, ? AS assigned_user_name, wp.created_at
             FROM workout_plans wp
             WHERE wp.assigned_user_id = ?
             ORDER BY wp.created_at DESC'
        );
        $stmt->execute([$auth['full_name'], $auth['id']]);
    }

    json_response(['plans' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = require_role(['admin', 'istruttore', 'autonomo']);
    $data = input_json();
    $name = clean_string($data['name'] ?? 'Nuovo programma', 160);
    $assignedUserId = can_manage($auth) ? (int) ($data['assigned_user_id'] ?? 0) : (int) $auth['id'];

    if ($name === '' || $assignedUserId <= 0) {
        json_response(['error' => 'Nome programma e atleta sono obbligatori'], 422);
    }

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('atleta', 'autonomo')");
    $userStmt->execute([$assignedUserId]);
    if (!$userStmt->fetch()) {
        json_response(['error' => 'Atleta non valido'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO workout_plans (name, assigned_user_id, created_by) VALUES (?, ?, ?)'
    );
    $stmt->execute([$name, $assignedUserId, $auth['id']]);
    $planId = (int) $pdo->lastInsertId();

    for ($day = 1; $day <= 7; $day++) {
        $dayStmt = $pdo->prepare(
            'INSERT INTO workout_days (workout_plan_id, day_number, title) VALUES (?, ?, ?)'
        );
        $dayStmt->execute([$planId, $day, 'Day ' . $day]);
    }

    log_event('workout_created', ['workout_plan_id' => $planId, 'assigned_user_id' => $assignedUserId]);
    json_response(['id' => $planId], 201);
}

json_response(['error' => 'Metodo non consentito'], 405);
