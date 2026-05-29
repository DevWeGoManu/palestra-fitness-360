<?php
require_once __DIR__ . '/../bootstrap.php';

$auth = require_user();
$pdo = Database::connection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    json_response(['error' => 'ID programma non valido'], 422);
}

function load_plan(PDO $pdo, int $id, ?array $auth = null): ?array
{
    $stmt = $pdo->prepare(
        'SELECT wp.*, u.full_name AS assigned_user_name
         FROM workout_plans wp
         LEFT JOIN users u ON u.id = wp.assigned_user_id
         WHERE wp.id = ?'
    );
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    if (!$plan) {
        return null;
    }

    $days = $pdo->prepare(
        'SELECT id, day_number, title FROM workout_days WHERE workout_plan_id = ? ORDER BY day_number ASC'
    );
    $days->execute([$id]);
    $plan['days'] = $days->fetchAll();

    $exercises = $pdo->prepare(
        'SELECT id, workout_day_id, block_type, name, sets, reps, weight, rest, notes, circuit_rounds, circuit_exercises, order_index
         FROM exercises
         WHERE workout_day_id IN (SELECT id FROM workout_days WHERE workout_plan_id = ?)
         ORDER BY order_index ASC, id ASC'
    );
    $exercises->execute([$id]);
    $byDay = [];
    foreach ($exercises->fetchAll() as $exercise) {
        $exercise = normalize_exercise_for_response($exercise);
        $byDay[$exercise['workout_day_id']][] = $exercise;
    }

    if ($auth && !can_manage($auth)) {
        $noteStmt = $pdo->prepare(
            'SELECT wen.exercise_id, wen.note
             FROM workout_exercise_notes wen
             JOIN exercises e ON e.id = wen.exercise_id
             JOIN workout_days wd ON wd.id = e.workout_day_id
             WHERE wd.workout_plan_id = ? AND wen.user_id = ?'
        );
        $noteStmt->execute([$id, (int) $auth['id']]);
        $notesByExercise = [];
        foreach ($noteStmt->fetchAll() as $note) {
            $notesByExercise[(int) $note['exercise_id']] = $note['note'];
        }
        foreach ($byDay as &$items) {
            foreach ($items as &$exercise) {
                $exercise['athlete_note'] = $notesByExercise[(int) $exercise['id']] ?? '';
            }
        }
    }

    foreach ($plan['days'] as &$day) {
        $day['exercises'] = $byDay[$day['id']] ?? [];
    }

    return $plan;
}

$plan = load_plan($pdo, $id, $auth);
if (!$plan) {
    json_response(['error' => 'Programma non trovato'], 404);
}
if (!can_manage($auth) && (int) $plan['assigned_user_id'] !== (int) $auth['id']) {
    json_response(['error' => 'Permesso negato'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['plan' => $plan]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['admin', 'istruttore', 'autonomo']);
    if (!can_manage($auth) && (int) $plan['assigned_user_id'] !== (int) $auth['id']) {
        json_response(['error' => 'Permesso negato'], 403);
    }
    $data = input_json();
    $validDayIds = array_map(static fn ($day) => (int) $day['id'], $plan['days']);
    $pdo->beginTransaction();

    $name = clean_string($data['name'] ?? $plan['name'], 160);
    $assignedUserId = can_manage($auth) ? (int) ($data['assigned_user_id'] ?? $plan['assigned_user_id']) : (int) $auth['id'];
    if ($name === '') {
        $pdo->rollBack();
        json_response(['error' => 'Nome programma obbligatorio'], 422);
    }
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('atleta', 'autonomo')");
    $userStmt->execute([$assignedUserId]);
    if (!$userStmt->fetch()) {
        $pdo->rollBack();
        json_response(['error' => 'Atleta non valido'], 422);
    }
    $stmt = $pdo->prepare('UPDATE workout_plans SET name = ?, assigned_user_id = ? WHERE id = ?');
    $stmt->execute([$name, $assignedUserId, $id]);

    foreach (($data['days'] ?? []) as $day) {
        $dayId = (int) ($day['id'] ?? 0);
        $title = clean_string($day['title'] ?? '', 120);
        $dayNumber = (int) ($day['day_number'] ?? 0);

        if ($dayId <= 0 || $dayNumber < 1 || $dayNumber > 7) {
            continue;
        }
        if (!in_array($dayId, $validDayIds, true)) {
            continue;
        }

        $updateDay = $pdo->prepare(
            'UPDATE workout_days SET title = ? WHERE id = ? AND workout_plan_id = ?'
        );
        $updateDay->execute([$title ?: 'Day ' . $dayNumber, $dayId, $id]);

        $deleteExercises = $pdo->prepare('DELETE FROM exercises WHERE workout_day_id = ?');
        $deleteExercises->execute([$dayId]);

        $insertExercise = $pdo->prepare(
            'INSERT INTO exercises (workout_day_id, block_type, name, sets, reps, weight, rest, notes, circuit_rounds, circuit_exercises, order_index)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (($day['exercises'] ?? []) as $index => $exercise) {
            $blockType = (($exercise['type'] ?? $exercise['block_type'] ?? 'exercise') === 'circuit') ? 'circuit' : 'exercise';
            $exerciseName = clean_string($exercise['name'] ?? ($blockType === 'circuit' ? 'Circuito' : ''), 160);
            if ($exerciseName === '') {
                continue;
            }
            $circuitRounds = '';
            $circuitExercises = null;
            if ($blockType === 'circuit') {
                $circuitRounds = clean_string($exercise['rounds'] ?? $exercise['circuit_rounds'] ?? $exercise['sets'] ?? '', 40);
                $circuitExercises = normalize_circuit_exercises_for_storage($exercise['exercises'] ?? $exercise['circuit_exercises'] ?? []);
                if ($circuitRounds === '' || !$circuitExercises) {
                    continue;
                }
            }
            $insertExercise->execute([
                $dayId,
                $blockType,
                $exerciseName,
                $blockType === 'exercise' ? clean_string($exercise['sets'] ?? '', 40) : '',
                $blockType === 'exercise' ? clean_string($exercise['reps'] ?? '', 40) : '',
                $blockType === 'exercise' ? clean_string($exercise['weight'] ?? '', 40) : '',
                clean_string($exercise['rest'] ?? '', 40),
                $blockType === 'exercise' ? clean_text($exercise['notes'] ?? '', 1000) : '',
                $blockType === 'circuit' ? $circuitRounds : null,
                $blockType === 'circuit' ? json_encode($circuitExercises, JSON_UNESCAPED_UNICODE) : null,
                (int) ($exercise['order_index'] ?? $index + 1),
            ]);
        }
    }

    $pdo->commit();
    log_event('workout_updated', ['workout_plan_id' => $id]);
    json_response(['plan' => load_plan($pdo, $id, $auth)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['admin', 'istruttore', 'autonomo']);
    if (!can_manage($auth) && (int) $plan['assigned_user_id'] !== (int) $auth['id']) {
        json_response(['error' => 'Permesso negato'], 403);
    }
    $stmt = $pdo->prepare('DELETE FROM workout_plans WHERE id = ?');
    $stmt->execute([$id]);
    log_event('workout_deleted', ['workout_plan_id' => $id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Metodo non consentito'], 405);

function normalize_exercise_for_response(array $exercise): array
{
    $type = ($exercise['block_type'] ?? 'exercise') === 'circuit' ? 'circuit' : 'exercise';
    $exercise['type'] = $type;
    unset($exercise['block_type']);

    if ($type === 'circuit') {
        $exercise['rounds'] = normalize_numeric_payload_value($exercise['circuit_rounds'] ?? '');
        $decoded = json_decode((string) ($exercise['circuit_exercises'] ?? '[]'), true);
        $exercise['exercises'] = is_array($decoded) ? normalize_circuit_exercises_for_response($decoded) : [];
        $exercise['sets'] = '';
        $exercise['reps'] = '';
        $exercise['weight'] = '';
        $exercise['rest'] = clean_string($exercise['rest'] ?? '', 40);
        $exercise['notes'] = '';
    } else {
        $exercise['rounds'] = '';
        $exercise['exercises'] = [];
    }

    unset($exercise['circuit_rounds'], $exercise['circuit_exercises']);
    return $exercise;
}

function normalize_circuit_exercises_for_storage(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clean_string($item['name'] ?? '', 160);
        $reps = clean_string($item['reps'] ?? '', 40);
        if ($name === '' || $reps === '') {
            continue;
        }
        $normalized[] = [
            'name' => $name,
            'reps' => normalize_numeric_payload_value($reps),
        ];
    }

    return $normalized;
}

function normalize_circuit_exercises_for_response(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clean_string($item['name'] ?? '', 160);
        $reps = clean_string($item['reps'] ?? '', 40);
        if ($name === '' || $reps === '') {
            continue;
        }
        $normalized[] = [
            'name' => $name,
            'reps' => normalize_numeric_payload_value($reps),
        ];
    }
    return $normalized;
}

function normalize_numeric_payload_value(mixed $value): int|string
{
    $clean = clean_string($value, 40);
    return ctype_digit($clean) ? (int) $clean : $clean;
}
