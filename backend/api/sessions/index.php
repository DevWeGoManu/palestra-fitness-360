<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = (int) ($_GET['user_id'] ?? $auth['id']);
    if (!can_manage($auth)) {
        $userId = (int) $auth['id'];
    }

    $stmt = $pdo->prepare(
        'SELECT ws.id, ws.completed_at, wp.id AS workout_plan_id, wp.name AS workout_plan_name,
                wd.id AS workout_day_id, wd.day_number, wd.title AS workout_day_title, u.full_name
         FROM workout_sessions ws
         JOIN workout_plans wp ON wp.id = ws.workout_plan_id
         LEFT JOIN workout_days wd ON wd.id = ws.workout_day_id
         JOIN users u ON u.id = ws.user_id
         WHERE ws.user_id = ?
         ORDER BY ws.completed_at DESC'
    );
    $stmt->execute([$userId]);

    json_response(['sessions' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json();
    $planId = (int) ($data['workout_plan_id'] ?? 0);
    $dayId = (int) ($data['workout_day_id'] ?? 0);
    if ($planId <= 0) {
        json_response(['error' => 'Programma non valido'], 422);
    }
    if ($dayId <= 0) {
        json_response(['error' => 'Giorno allenamento non valido'], 422);
    }

    $planStmt = $pdo->prepare('SELECT assigned_user_id FROM workout_plans WHERE id = ?');
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();
    if (!$plan) {
        json_response(['error' => 'Programma non trovato'], 404);
    }

    $userId = (int) $plan['assigned_user_id'];
    if (!can_manage($auth) && $userId !== (int) $auth['id']) {
        json_response(['error' => 'Permesso negato'], 403);
    }

    $dayStmt = $pdo->prepare(
        'SELECT wd.id, wd.day_number, wd.title, COUNT(e.id) AS exercise_count
         FROM workout_days wd
         LEFT JOIN exercises e ON e.workout_day_id = wd.id
         WHERE wd.id = ? AND wd.workout_plan_id = ?
         GROUP BY wd.id, wd.day_number, wd.title'
    );
    $dayStmt->execute([$dayId, $planId]);
    $day = $dayStmt->fetch();
    if (!$day) {
        json_response(['error' => 'Day non trovato per questo programma'], 404);
    }
    if ((int) $day['exercise_count'] === 0) {
        json_response(['error' => 'Questo day non contiene esercizi'], 422);
    }

    $existingStmt = $pdo->prepare(
        'SELECT id, workout_day_id, completed_at
         FROM workout_sessions
         WHERE workout_plan_id = ?
           AND user_id = ?
           AND DATE(completed_at) = CURRENT_DATE
         ORDER BY completed_at DESC
         LIMIT 1'
    );
    $existingStmt->execute([$planId, $userId]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        if ((int) $existing['workout_day_id'] !== $dayId) {
            $updateStmt = $pdo->prepare(
                'UPDATE workout_sessions
                 SET workout_day_id = ?, completed_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $updateStmt->execute([$dayId, (int) $existing['id']]);
            log_event('workout_completed_day_changed', [
                'workout_session_id' => (int) $existing['id'],
                'workout_plan_id' => $planId,
                'workout_day_id' => $dayId,
                'target_user_id' => $userId,
            ]);
        }
        json_response([
            'id' => (int) $existing['id'],
            'workout_day_id' => $dayId,
            'day_number' => (int) $day['day_number'],
            'day_title' => $day['title'],
            'completed_at' => $existing['completed_at'],
            'already_completed' => true,
            'message' => $day['title'] . ' completato',
        ]);
    }

    $stmt = $pdo->prepare('INSERT INTO workout_sessions (workout_plan_id, workout_day_id, user_id) VALUES (?, ?, ?)');
    $stmt->execute([$planId, $dayId, $userId]);

    $sessionId = (int) $pdo->lastInsertId();
    log_event('workout_completed', [
        'workout_session_id' => $sessionId,
        'workout_plan_id' => $planId,
        'workout_day_id' => $dayId,
        'target_user_id' => $userId,
    ]);
    json_response([
        'id' => $sessionId,
        'workout_day_id' => $dayId,
        'day_number' => (int) $day['day_number'],
        'day_title' => $day['title'],
        'already_completed' => false,
        'message' => $day['title'] . ' completato',
    ], 201);
}

json_response(['error' => 'Metodo non consentito'], 405);
