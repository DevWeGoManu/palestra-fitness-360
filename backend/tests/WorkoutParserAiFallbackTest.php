<?php

require_once __DIR__ . '/../lib/Validation.php';
require_once __DIR__ . '/../lib/WorkoutParserAiFallback.php';

$failures = [];

function ai_parser_test_assert_equals(mixed $expected, mixed $actual, string $label): void
{
    global $failures;
    if ($expected !== $actual) {
        $failures[] = [
            'label' => $label,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }
}

$fallback = workout_parser_evaluate_ai_fallback_need('Superset 3 giri: panca 10 reps + rematore 10 reps', []);
ai_parser_test_assert_equals(true, $fallback['needed'] ?? null, 'superset empty parse needs fallback');
ai_parser_test_assert_equals(['no_exercises_detected', 'superset_keyword_without_superset_block'], $fallback['reasons'] ?? null, 'superset fallback reasons');

$fallback = workout_parser_evaluate_ai_fallback_need('AMRAP 12 minuti: 5 pull up, 10 push up, 15 squat', [
    [
        'day_number' => 1,
        'exercises' => [
            ['type' => 'exercise', 'name' => 'Pull up', 'sets' => '', 'reps' => '5'],
        ],
    ],
]);
ai_parser_test_assert_equals(true, $fallback['needed'] ?? null, 'amrap keyword needs fallback when not represented');

$fallback = workout_parser_evaluate_ai_fallback_need('Fai un circuito per 4 giri con 1km corsa, 10 push up, 10 squat, recupero 1 minuto', [
    [
        'day_number' => 1,
        'exercises' => [
            ['type' => 'circuit', 'name' => 'Circuito', 'rounds' => 4],
        ],
    ],
]);
ai_parser_test_assert_equals(false, $fallback['needed'] ?? null, 'structured circuit does not need fallback');

$sanitized = workout_parser_sanitize_ai_response([
    'debug' => '<script>ignored</script>',
    'days' => [
        [
            'day_number' => 2,
            'title' => 'Upper',
            'unknown' => 'ignored',
            'blocks' => [
                [
                    'type' => 'exercise',
                    'name' => 'Panca',
                    'sets' => '3',
                    'reps' => '10',
                    'html' => '<b>ignored</b>',
                ],
                [
                    'type' => 'circuit',
                    'rounds' => '4',
                    'rest' => '1 minuto',
                    'exercises' => [
                        ['name' => 'corsa', 'quantity' => '1 km', 'extra' => 'ignored'],
                        ['name' => 'push up', 'reps' => '10'],
                    ],
                ],
                [
                    'type' => 'superset',
                    'rounds' => '3',
                    'exercises' => [
                        ['name' => 'panca', 'reps' => '10'],
                        ['name' => 'rematore', 'reps' => '10'],
                    ],
                ],
            ],
        ],
    ],
]);

ai_parser_test_assert_equals([], $sanitized['errors'] ?? null, 'sanitized ai response has no errors');
ai_parser_test_assert_equals(2, $sanitized['days'][0]['day_number'] ?? null, 'sanitized day number');
ai_parser_test_assert_equals(3, count($sanitized['days'][0]['exercises'] ?? []), 'sanitized block count');
ai_parser_test_assert_equals('exercise', $sanitized['days'][0]['exercises'][0]['type'] ?? null, 'sanitized exercise type');
ai_parser_test_assert_equals('circuit', $sanitized['days'][0]['exercises'][1]['type'] ?? null, 'sanitized circuit type');
ai_parser_test_assert_equals([
    ['name' => 'corsa', 'reps' => '1 km'],
    ['name' => 'push up', 'reps' => 10],
], $sanitized['days'][0]['exercises'][1]['exercises'] ?? null, 'sanitized circuit items');
ai_parser_test_assert_equals('superset', $sanitized['days'][0]['exercises'][2]['type'] ?? null, 'sanitized superset type');

$stub = parseWorkoutWithAiFallback('AMRAP 12 minuti: 5 pull up, 10 push up, 15 squat');
ai_parser_test_assert_equals(false, $stub['available'] ?? null, 'ai fallback stub disabled');
ai_parser_test_assert_equals([], $stub['days'] ?? null, 'ai fallback stub returns no days');

if ($failures) {
    echo "WorkoutParserAiFallbackTest FAILED\n";
    foreach ($failures as $failure) {
        echo '- ' . $failure['label'] . "\n";
        echo '  expected: ' . var_export($failure['expected'], true) . "\n";
        echo '  actual:   ' . var_export($failure['actual'], true) . "\n";
    }
    exit(1);
}

echo "WorkoutParserAiFallbackTest OK\n";
