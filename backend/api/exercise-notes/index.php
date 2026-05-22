<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
$exerciseId = (int) ($data['exercise_id'] ?? 0);
$note = clean_text($data['note'] ?? '', 1000);

if ($exerciseId <= 0) {
    json_response(['error' => 'Esercizio non valido'], 422);
}

$stmt = $pdo->prepare(
    'SELECT e.id, wp.assigned_user_id
     FROM exercises e
     JOIN workout_days wd ON wd.id = e.workout_day_id
     JOIN workout_plans wp ON wp.id = wd.workout_plan_id
     WHERE e.id = ?'
);
$stmt->execute([$exerciseId]);
$exercise = $stmt->fetch();

if (!$exercise) {
    json_response(['error' => 'Esercizio non trovato'], 404);
}

if (!can_manage($auth) && (int) $exercise['assigned_user_id'] !== (int) $auth['id']) {
    json_response(['error' => 'Permesso negato'], 403);
}

$upsert = $pdo->prepare(
    'INSERT INTO workout_exercise_notes (exercise_id, user_id, note)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = CURRENT_TIMESTAMP'
);
$upsert->execute([$exerciseId, (int) $auth['id'], $note]);

log_event('exercise_note_saved', ['exercise_id' => $exerciseId]);
json_response(['ok' => true, 'exercise_id' => $exerciseId, 'note' => $note]);
