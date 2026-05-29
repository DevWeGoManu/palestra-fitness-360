<?php

const WORKOUT_AI_FALLBACK_KEYWORDS = ['circuito', 'superset', 'emom', 'amrap'];

function workout_parser_evaluate_ai_fallback_need(string $text, array $days): array
{
    $exerciseCount = 0;
    $blockTypes = [];
    $names = [];

    foreach ($days as $day) {
        foreach (($day['exercises'] ?? []) as $exercise) {
            if (!is_array($exercise)) {
                continue;
            }
            $exerciseCount++;
            $blockTypes[] = strtolower((string) ($exercise['type'] ?? 'exercise'));
            $names[] = strtolower((string) ($exercise['name'] ?? ''));
        }
    }

    $textLower = strtolower($text);
    $reasons = [];

    if ($exerciseCount === 0) {
        $reasons[] = 'no_exercises_detected';
    }

    if (str_contains($textLower, 'circuito') && !in_array('circuit', $blockTypes, true)) {
        $reasons[] = 'circuit_keyword_without_circuit_block';
    }

    if (str_contains($textLower, 'superset') && !in_array('superset', $blockTypes, true)) {
        $reasons[] = 'superset_keyword_without_superset_block';
    }

    foreach (['emom', 'amrap'] as $keyword) {
        if (!str_contains($textLower, $keyword)) {
            continue;
        }
        $hasNamedKeyword = array_reduce(
            $names,
            static fn (bool $found, string $name): bool => $found || str_contains($name, $keyword),
            false
        );
        if (!$hasNamedKeyword) {
            $reasons[] = $keyword . '_keyword_not_represented';
        }
    }

    $confidence = $exerciseCount > 0 ? 0.82 : 0.0;
    if ($reasons) {
        $confidence = min($confidence, 0.45);
    }

    return [
        'needed' => $reasons !== [],
        'confidence' => $confidence,
        'reasons' => array_values(array_unique($reasons)),
    ];
}

function parseWorkoutWithAiFallback(string $text): array
{
    // Hook point for a future backend-only AI provider.
    // The provider must return JSON matching the schema documented in docs/workout-parser-ai-fallback.md.
    // Never call an AI provider from the frontend and never expose API keys to the client.
    return [
        'available' => false,
        'days' => [],
        'warnings' => [],
        'errors' => ['AI fallback non configurato'],
    ];
}

function workout_parser_sanitize_ai_response(mixed $payload): array
{
    if (is_string($payload)) {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return ['days' => [], 'warnings' => [], 'errors' => ['Risposta AI non valida']];
        }
        $payload = $decoded;
    }

    if (!is_array($payload)) {
        return ['days' => [], 'warnings' => [], 'errors' => ['Risposta AI non valida']];
    }

    $rawDays = $payload['days'] ?? $payload;
    if (!is_array($rawDays)) {
        return ['days' => [], 'warnings' => [], 'errors' => ['Schema AI non valido']];
    }

    $days = [];
    $warnings = [];

    foreach ($rawDays as $index => $day) {
        if (!is_array($day)) {
            continue;
        }

        $dayNumber = max(1, min(7, (int) ($day['day_number'] ?? $day['day'] ?? $index + 1)));
        $blocks = workout_parser_sanitize_ai_blocks($day['exercises'] ?? $day['blocks'] ?? []);
        if (!$blocks) {
            $warnings[] = 'Un giorno restituito dall AI non contiene blocchi validi.';
            continue;
        }

        $days[] = [
            'day_number' => $dayNumber,
            'name' => clean_string($day['name'] ?? 'Day ' . $dayNumber, 80),
            'title' => clean_string($day['title'] ?? $day['name'] ?? 'Day ' . $dayNumber, 80),
            'exercises' => $blocks,
        ];
    }

    return [
        'days' => $days,
        'warnings' => array_values(array_unique($warnings)),
        'errors' => $days ? [] : ['Nessun blocco valido nella risposta AI'],
    ];
}

function workout_parser_sanitize_ai_blocks(mixed $blocks): array
{
    if (!is_array($blocks)) {
        return [];
    }

    $sanitized = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $type = strtolower(clean_string($block['type'] ?? 'exercise', 30));
        $next = match ($type) {
            'circuit' => workout_parser_sanitize_ai_circuit($block),
            'superset' => workout_parser_sanitize_ai_superset($block),
            default => workout_parser_sanitize_ai_exercise($block),
        };

        if ($next !== null) {
            $next['order_index'] = count($sanitized) + 1;
            $sanitized[] = $next;
        }
    }

    return $sanitized;
}

function workout_parser_sanitize_ai_exercise(array $block): ?array
{
    $name = clean_string($block['name'] ?? '', 160);
    if ($name === '') {
        return null;
    }

    return [
        'type' => 'exercise',
        'name' => $name,
        'sets' => clean_string($block['sets'] ?? '', 40),
        'reps' => clean_string($block['reps'] ?? $block['quantity'] ?? '', 40),
        'weight' => clean_string($block['weight'] ?? '', 40),
        'rest' => clean_string($block['rest'] ?? '', 40),
        'notes' => clean_text($block['notes'] ?? '', 1000),
    ];
}

function workout_parser_sanitize_ai_circuit(array $block): ?array
{
    $rounds = clean_string($block['rounds'] ?? $block['sets'] ?? '', 40);
    $items = workout_parser_sanitize_ai_circuit_items($block['exercises'] ?? []);
    if ($rounds === '' || !$items) {
        return null;
    }

    return [
        'type' => 'circuit',
        'name' => clean_string($block['name'] ?? 'Circuito', 160) ?: 'Circuito',
        'rounds' => ctype_digit($rounds) ? (int) $rounds : $rounds,
        'exercises' => $items,
        'sets' => '',
        'reps' => '',
        'weight' => '',
        'rest' => clean_string($block['rest'] ?? '', 40),
        'notes' => '',
    ];
}

function workout_parser_sanitize_ai_superset(array $block): ?array
{
    $items = workout_parser_sanitize_ai_circuit_items($block['exercises'] ?? []);
    if (!$items) {
        return null;
    }

    return [
        'type' => 'superset',
        'name' => clean_string($block['name'] ?? 'Superset', 160) ?: 'Superset',
        'rounds' => clean_string($block['rounds'] ?? $block['sets'] ?? '', 40),
        'exercises' => $items,
        'sets' => '',
        'reps' => '',
        'weight' => '',
        'rest' => clean_string($block['rest'] ?? '', 40),
        'notes' => clean_text($block['notes'] ?? '', 1000),
    ];
}

function workout_parser_sanitize_ai_circuit_items(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }

    $sanitized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clean_string($item['name'] ?? '', 160);
        $reps = clean_string($item['reps'] ?? $item['quantity'] ?? '', 40);
        if ($name === '' || $reps === '') {
            continue;
        }
        $sanitized[] = [
            'name' => $name,
            'reps' => ctype_digit($reps) ? (int) $reps : $reps,
        ];
    }

    return $sanitized;
}
