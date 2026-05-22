<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();

if (can_manage($auth)) {
    $athletes = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'atleta'")->fetchColumn();
    $plans = (int) $pdo->query('SELECT COUNT(*) FROM workout_plans')->fetchColumn();
    $stmt = $pdo->query(
        'SELECT ws.completed_at, wp.name AS workout_plan_name, wd.title AS workout_day_title, u.full_name
         FROM workout_sessions ws
         JOIN workout_plans wp ON wp.id = ws.workout_plan_id
         LEFT JOIN workout_days wd ON wd.id = ws.workout_day_id
         JOIN users u ON u.id = ws.user_id
         ORDER BY ws.completed_at DESC
         LIMIT 5'
    );
    json_response([
        'stats' => [
            'athletes' => $athletes,
            'active_plans' => $plans,
            'recent_sessions' => $stmt->fetchAll(),
        ],
    ]);
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM workout_sessions WHERE user_id = ?');
$countStmt->execute([$auth['id']]);
$lastStmt = $pdo->prepare(
    'SELECT ws.completed_at, wp.name AS workout_plan_name, wd.title AS workout_day_title
     FROM workout_sessions ws
     JOIN workout_plans wp ON wp.id = ws.workout_plan_id
     LEFT JOIN workout_days wd ON wd.id = ws.workout_day_id
     WHERE ws.user_id = ?
     ORDER BY ws.completed_at DESC
     LIMIT 1'
);
$lastStmt->execute([$auth['id']]);
$planStmt = $pdo->prepare(
    'SELECT id, name FROM workout_plans WHERE assigned_user_id = ? ORDER BY created_at DESC LIMIT 1'
);
$planStmt->execute([$auth['id']]);

json_response([
    'stats' => [
        'completed_count' => (int) $countStmt->fetchColumn(),
        'last_session' => $lastStmt->fetch() ?: null,
        'active_plan' => $planStmt->fetch() ?: null,
    ],
]);
