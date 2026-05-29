<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../lib/WorkoutParser.php';
require_once __DIR__ . '/../../lib/WorkoutParserAiFallback.php';

$user = require_role(['admin', 'istruttore', 'autonomo']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo non consentito'], 405);
}

$data = input_json();
$rawText = $data['text'] ?? '';

if (!is_string($rawText) && !is_numeric($rawText)) {
    json_response(['error' => 'Testo scheda non valido'], 422);
}

$rawText = (string) $rawText;

if (trim($rawText) === '') {
    json_response(['error' => 'Inserisci il testo della scheda da analizzare'], 422);
}

if (!rate_limit('workout_parser', client_ip() . '|' . $user['id'], 40, 300)) {
    log_event('workout_parser_rate_limited', ['user_id' => (int) $user['id']]);
    json_response(['error' => 'Troppe analisi parser. Riprova tra qualche minuto'], 429);
}

if (strlen($rawText) > WORKOUT_PARSER_MAX_INPUT_LENGTH) {
    json_response(['error' => 'Testo troppo lungo. Limite massimo: ' . WORKOUT_PARSER_MAX_INPUT_LENGTH . ' caratteri'], 422);
}

$text = clean_text($rawText, WORKOUT_PARSER_MAX_INPUT_LENGTH);

[$days, $warnings] = workout_parser_parse_text($text);
$fallback = workout_parser_evaluate_ai_fallback_need($text, $days);

if (!$days) {
    $aiResult = parseWorkoutWithAiFallback($text);
    if (($aiResult['available'] ?? false) && !empty($aiResult['days'])) {
        $sanitized = workout_parser_sanitize_ai_response(['days' => $aiResult['days']]);
        if (!empty($sanitized['days'])) {
            log_event('workout_text_parsed_ai_fallback', ['days' => count($sanitized['days'])]);
            json_response([
                'days' => $sanitized['days'],
                'warnings' => array_values(array_unique(array_merge($warnings, $sanitized['warnings'] ?? []))),
                'parser' => [
                    'source' => 'ai_fallback',
                    'fallback' => $fallback,
                ],
            ]);
        }
    }

    json_response([
        'error' => 'Non ho trovato esercizi riconoscibili. Prova con un formato tipo: Day 1: squat 3x5 con 80kg, plank 30s per 3 serie',
        'parser' => [
            'source' => 'deterministic',
            'fallback' => $fallback,
            'ai_available' => (bool) ($aiResult['available'] ?? false),
        ],
    ], 422);
}

log_event('workout_text_parsed', ['days' => count($days)]);
json_response([
    'days' => $days,
    'warnings' => $warnings,
    'parser' => [
        'source' => 'deterministic',
        'fallback' => $fallback,
    ],
]);
