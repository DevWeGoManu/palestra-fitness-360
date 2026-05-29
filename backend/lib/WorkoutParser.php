<?php

const WORKOUT_PARSER_WEIGHT_UNIT = '(?:kg|kgs|chili|kili|chilogrammi)';
const WORKOUT_PARSER_TIME_UNIT = '(?:s|sec|secondi|min|m|minuti|\')';
const WORKOUT_PARSER_MAX_INPUT_LENGTH = 6000;

function workout_parser_parse_text(string $text): array
{
    $text = workout_parser_normalize_text($text);
    $sections = workout_parser_split_day_sections($text);
    $days = [];
    $warnings = [];

    foreach ($sections as $section) {
        $dayNumber = max(1, min(7, (int) $section['day_number']));
        $exercises = [];
        $parts = workout_parser_normalize_exercise_parts(
            preg_split('/(?:\n+|;|,)/u', $section['body']) ?: []
        );

        foreach ($parts as $part) {
            $parsed = workout_parser_parse_exercise_line($part);
            if ($parsed === null) {
                continue;
            }
            $parsed['order_index'] = count($exercises) + 1;
            $exercises[] = $parsed;

            if ($parsed['name'] === '') {
                $warnings[] = 'Un esercizio in Day ' . $dayNumber . ' non ha un nome chiaro.';
            }
        }

        if (!$exercises) {
            continue;
        }

        $days[] = [
            'day_number' => $dayNumber,
            'name' => 'Day ' . $dayNumber,
            'title' => 'Day ' . $dayNumber,
            'exercises' => $exercises,
        ];
    }

    return [$days, array_values(array_unique($warnings))];
}

function workout_parser_normalize_text(string $text): string
{
    $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
    $text = preg_replace('/[×✕]/u', 'x', $text) ?? $text;
    $text = preg_replace('/[“”″]/u', '"', $text) ?? $text;
    $text = preg_replace('/[‘’]/u', "'", $text) ?? $text;
    $text = preg_replace('/[–—]/u', '-', $text) ?? $text;
    $text = preg_replace('/(?:^|\n)\s*(?:✅|➡️?|👉|•|·|▪|▫|\*|-)\s*/u', "\n", $text) ?? $text;
    $text = preg_replace('/(?<!^)(?<!\n)\s+\b(day|giorno)\s*([1-7])\b\s*:?\s*/iu', "\n$1 $2: ", $text) ?? $text;
    $text = preg_replace(
        '/\[(?:\d{1,2}:\d{2}(?::\d{2})?(?:,\s*\d{1,2}\/\d{1,2}\/\d{2,4})?|\d{1,2}\/\d{1,2}(?:\/\d{2,4})?,\s*\d{1,2}:\d{2}(?::\d{2})?)\]\s*[^:\n]{1,120}:\s*/u',
        '',
        $text
    ) ?? $text;
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
    return trim($text);
}

function workout_parser_normalize_exercise_parts(array $parts): array
{
    $normalized = [];
    $cleanParts = [];

    foreach ($parts as $part) {
        $part = workout_parser_strip_leading_marker(clean_string($part, 500));
        if ($part === '') {
            continue;
        }
        $cleanParts[] = $part;
    }

    $count = count($cleanParts);
    for ($index = 0; $index < $count; $index++) {
        $part = $cleanParts[$index];

        $inlineCircuit = workout_parser_parse_inline_natural_circuit($part);
        if ($inlineCircuit !== null) {
            $normalized[] = workout_parser_build_circuit_block(
                $inlineCircuit['rounds'],
                $inlineCircuit['items'],
                $inlineCircuit['rest']
            );
            continue;
        }

        $naturalCircuitRounds = workout_parser_extract_natural_circuit_rounds($part);
        if ($naturalCircuitRounds !== '') {
            $circuitItems = [];
            $circuitRest = '';
            $lookahead = $index + 1;

            while ($lookahead < $count) {
                $candidate = $cleanParts[$lookahead];
                $rest = workout_parser_extract_circuit_rest_fragment($candidate);

                if ($rest !== '') {
                    if ($circuitRest === '') {
                        $circuitRest = $rest;
                    }
                    $lookahead++;
                    continue;
                }

                if (workout_parser_is_circuit_item_fragment($candidate)) {
                    $circuitItems[] = $candidate;
                    $lookahead++;
                    continue;
                }

                break;
            }

            if (count($circuitItems) > 1) {
                $normalized[] = workout_parser_build_circuit_block($naturalCircuitRounds, $circuitItems, $circuitRest);
                $index = $lookahead - 1;
                continue;
            }
        }

        if (workout_parser_is_rest_only_fragment($part)) {
            if ($normalized) {
                $lastIndex = count($normalized) - 1;
                $normalized[$lastIndex] .= ' recupero ' . workout_parser_extract_circuit_rest_fragment($part);
            }
            continue;
        }

        if ($normalized && workout_parser_is_round_multiplier_fragment($part)) {
            $rounds = workout_parser_extract_round_multiplier($part);
            $lastIndex = count($normalized) - 1;
            $circuitItems = [];

            while ($lastIndex >= 0 && workout_parser_is_leading_reps_exercise($normalized[$lastIndex])) {
                array_unshift($circuitItems, $normalized[$lastIndex]);
                $lastIndex--;
            }

            if (count($circuitItems) > 1 && $rounds !== '') {
                array_splice(
                    $normalized,
                    $lastIndex + 1,
                    count($circuitItems),
                    [workout_parser_build_circuit_block($rounds, $circuitItems)]
                );
                continue;
            }

            if (count($circuitItems) === 1 && $rounds !== '') {
                $normalized[$lastIndex + 1] .= ' x' . $rounds;
                continue;
            }

            continue;
        }

        if ($normalized && workout_parser_is_parameter_fragment($part)) {
            $lastIndex = count($normalized) - 1;
            $normalized[$lastIndex] .= ' ' . $part;
            continue;
        }

        $normalized[] = $part;
    }

    return $normalized;
}

function workout_parser_strip_leading_marker(string $part): string
{
    $part = preg_replace('/^\s*(?:[-*•·▪▫]+|\d+[\).:-])\s*/u', '', $part) ?? $part;
    $part = workout_parser_strip_whatsapp_prefix($part);
    return trim($part);
}

function workout_parser_strip_whatsapp_prefix(string $part): string
{
    $part = preg_replace(
        '/^\s*\[(?:\d{1,2}:\d{2}(?::\d{2})?(?:,\s*\d{1,2}\/\d{1,2}\/\d{2,4})?|\d{1,2}\/\d{1,2}(?:\/\d{2,4})?,\s*\d{1,2}:\d{2}(?::\d{2})?)\]\s*[^:\n]{1,120}:\s*/u',
        '',
        $part
    ) ?? $part;

    return trim($part);
}

function workout_parser_is_parameter_fragment(string $part): bool
{
    return (bool) preg_match(
        '/^(?:\d+\s*[xX]\s*\d+|\d+\s+al\s+minuto\b|[xX]\s*(?:max|\d+)\s*(?:minuti|min|m)?\b|(?:max|\d+)\s+(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '|(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '|recupero\b|rest\b)/iu',
        $part
    );
}

function workout_parser_is_round_multiplier_fragment(string $part): bool
{
    return (bool) preg_match('/^[xX]\s*\d+\s*$/u', $part);
}

function workout_parser_extract_round_multiplier(string $part): string
{
    if (!preg_match('/^[xX]\s*(\d+)\s*$/u', $part, $match)) {
        return '';
    }

    return $match[1];
}

function workout_parser_extract_natural_circuit_rounds(string $part): string
{
    $part = trim($part, " \t\n\r\0\x0B-:.");

    if (preg_match('/^(?:crea\s+un\s+|fai\s+un\s+)?circuito\s+(?:di|per)?\s*[xX]?\s*(\d+)\s*(?:giri|rounds?|serie)?(?:\s+con(?:\s+i\s+seguenti\s+esercizi)?)?\s*$/iu', $part, $match)) {
        return $match[1];
    }

    if (preg_match('/^(\d+)\s*giri\s*(?:di|con)?\s*$/iu', $part, $match)) {
        return $match[1];
    }

    return '';
}

function workout_parser_parse_inline_natural_circuit(string $part): ?array
{
    $part = trim($part);
    $patterns = [
        '/^(?:crea\s+un\s+|fai\s+un\s+)?circuito\s+(?:di|per)?\s*[xX]?\s*(\d+)\s*(?:giri|rounds?|serie)?(?:\s+con(?:\s+i\s+seguenti\s+esercizi)?)?\s*:?\s+(.+)$/iu',
        '/^(\d+)\s*giri\s*(?:di|con)?\s*:?\s+(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $part, $match)) {
            continue;
        }

        $tail = clean_string($match[2] ?? '', 500);
        $rest = workout_parser_extract_circuit_rest_fragment($tail);
        $tail = workout_parser_remove_circuit_rest_fragment($tail);
        $items = workout_parser_extract_circuit_items_from_text($tail);

        if (count($items) > 1) {
            return [
                'rounds' => $match[1],
                'items' => $items,
                'rest' => $rest,
            ];
        }
    }

    return null;
}

function workout_parser_extract_circuit_rest_fragment(string $part): string
{
    if (preg_match('/\b(?:con\s+)?(?:un\s+)?(?:recupero|rest|rec\.?)\s*(?:di\s*)?(\d+)\s*(minuto|minuti|min|m|secondi|sec|s)\b/iu', $part, $match)
        || preg_match('/\b(?:con\s+)?(?:un\s+)?(?:recupero|rest|rec\.?)\s*(?:di\s*)?(\d+)\s*([\'’])/iu', $part, $match)) {
        return workout_parser_format_circuit_rest($match[1], $match[2]);
    }

    if (preg_match('/\b(?:con\s*)?(\d+)\s*(minuto|minuti|min|m|secondi|sec|s)\s*(?:di\s*)?(?:recupero|rest)\b/iu', $part, $match)
        || preg_match('/\b(?:con\s*)?(\d+)\s*([\'’])\s*(?:di\s*)?(?:recupero|rest)\b/iu', $part, $match)) {
        return workout_parser_format_circuit_rest($match[1], $match[2]);
    }

    return '';
}

function workout_parser_remove_circuit_rest_fragment(string $part): string
{
    $patterns = [
        '/\b(?:con\s+)?(?:un\s+)?(?:recupero|rest|rec\.?)\s*(?:di\s*)?\d+\s*(?:minuto|minuti|min|m|secondi|sec|s|[\'’])\b(?:\s*(?:per|a|alla|al)\s+(?:giro|round|serie|fine|fine\s+circuito))?/iu',
        '/\b(?:con\s+)?(?:un\s+)?(?:recupero|rest|rec\.?)\s*(?:di\s*)?\d+\s*[\'’](?:\s*(?:per|a|alla|al)\s+(?:giro|round|serie|fine|fine\s+circuito))?/iu',
        '/\b(?:con\s*)?\d+\s*(?:minuto|minuti|min|m|secondi|sec|s|[\'’])\s*(?:di\s*)?(?:recupero|rest)\b(?:\s*(?:per|a|alla|al)\s+(?:giro|round|serie|fine|fine\s+circuito))?/iu',
        '/\b(?:con\s*)?\d+\s*[\'’]\s*(?:di\s*)?(?:recupero|rest)\b(?:\s*(?:per|a|alla|al)\s+(?:giro|round|serie|fine|fine\s+circuito))?/iu',
    ];
    $part = preg_replace($patterns, ' ', $part) ?? $part;
    $part = preg_replace('/\s+/u', ' ', $part) ?? $part;
    return trim($part, " \t\n\r\0\x0B,;:.");
}

function workout_parser_is_rest_only_fragment(string $part): bool
{
    if (workout_parser_extract_circuit_rest_fragment($part) === '') {
        return false;
    }

    $remaining = workout_parser_remove_circuit_rest_fragment($part);
    $remaining = preg_replace('/\b(?:con|un|una|di|per|a|al|alla|fine|circuito|giro|round|serie)\b/iu', ' ', $remaining) ?? $remaining;
    $remaining = preg_replace('/\s+/u', ' ', $remaining) ?? $remaining;
    return trim($remaining, " \t\n\r\0\x0B,;:.") === '';
}

function workout_parser_format_circuit_rest(string $value, string $unit): string
{
    $unit = strtolower($unit);
    if (in_array($unit, ['minuto', 'minuti'], true)) {
        return $value . ' ' . ((int) $value === 1 ? 'minuto' : 'minuti');
    }

    if ($unit === 'secondi') {
        return $value . ' secondi';
    }

    if (in_array($unit, ["'", '’'], true)) {
        return $value . "'";
    }

    return $value . $unit;
}

function workout_parser_is_circuit_item_fragment(string $part): bool
{
    if (workout_parser_extract_circuit_rest_fragment($part) !== '') {
        return false;
    }

    if (preg_match('/\b(?:serie|sets?|ripetizioni|reps?|rep|recupero|rest|rec|al\s+minuto|' . WORKOUT_PARSER_WEIGHT_UNIT . ')\b/iu', $part)) {
        return false;
    }

    return (bool) preg_match('/^\d+\s*(?:km|m|metri|metro)?\s*(?:di\s+)?[\p{L}][\p{L}\p{N}\s\'-]{0,100}$/iu', $part);
}

function workout_parser_extract_circuit_items_from_text(string $text): array
{
    $text = workout_parser_remove_circuit_rest_fragment($text);
    $text = preg_replace('/\s*[,;]\s*/u', ' ', $text) ?? $text;
    preg_match_all(
        '/\d+\s*(?:km|m|metri|metro)?\s*(?:di\s+)?[^\d,;:]+?(?=\s+\d+\s*(?:km|m|metri|metro)?\s|$)/iu',
        $text,
        $matches
    );

    return array_values(array_filter(array_map(
        static fn (string $item): string => clean_string($item, 120),
        $matches[0] ?? []
    ), static fn (string $item): bool => workout_parser_is_circuit_item_fragment($item)));
}

function workout_parser_build_circuit_block(string $rounds, array $items, string $rest = ''): string
{
    $restPart = $rest !== '' ? ' rest ' . $rest : '';
    return 'Circuito x' . $rounds . $restPart . ': ' . implode(' | ', array_map(static fn (string $item): string => trim($item), $items));
}

function workout_parser_is_leading_reps_exercise(string $part): bool
{
    if (preg_match('/\b(?:serie|sets?|ripetizioni|reps?|rep|recupero|rest|rec|al\s+minuto|' . WORKOUT_PARSER_WEIGHT_UNIT . ')\b/iu', $part)) {
        return false;
    }

    return (bool) preg_match('/^\d+\s+[\p{L}][\p{L}\p{N}\s\'-]{0,100}$/u', $part);
}

function workout_parser_split_day_sections(string $text): array
{
    $pattern = '/\b(?:day|giorno)\s*([1-7])\b\s*:?\s*/iu';
    if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
        return [['day_number' => 1, 'body' => $text]];
    }

    $sections = [];
    $count = count($matches[0]);
    for ($index = 0; $index < $count; $index++) {
        $dayNumber = (int) $matches[1][$index][0];
        $bodyStart = $matches[0][$index][1] + strlen($matches[0][$index][0]);
        $bodyEnd = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($text);
        $body = substr($text, $bodyStart, $bodyEnd - $bodyStart);
        $sections[] = ['day_number' => $dayNumber, 'body' => $body];
    }

    return $sections;
}

function workout_parser_parse_exercise_line(string $line): ?array
{
    $original = clean_string($line, 400);
    if ($original === '') {
        return null;
    }
    $original = preg_replace('/["\x{201C}\x{201D}\x{2033}]/u', 's', $original) ?? $original;

    $sets = '';
    $reps = '';
    $weight = '';
    $rest = '';
    $notes = '';
    $seriesMatchStart = null;
    $seriesMatchEnd = null;
    $nameOverride = null;

    if (preg_match('/^Circuito\s+[xX]\s*(\d+)(?:\s+rest\s+([^:]+))?\s*:\s*(.+)$/u', $original, $match)) {
        return [
            'type' => 'circuit',
            'name' => 'Circuito',
            'rounds' => (int) $match[1],
            'exercises' => workout_parser_parse_circuit_items($match[3]),
            'sets' => '',
            'reps' => '',
            'weight' => '',
            'rest' => clean_string($match[2] ?? '', 80),
            'notes' => '',
        ];
    }

    if (preg_match('/\b(\d+)\s*(?:s|sec|secondi)\s*(?:per|x)\s*(\d+)\s*(?:serie|sets?)\b/iu', $original, $match)) {
        $reps = $match[1] . 's';
        $sets = $match[2];
    } elseif (preg_match('/\b(\d+)\s*(?:serie|sets?)\s*(?:da|x|per)\s*(\d+(?:\s*\/\s*\d+)?)\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu', $original, $match)) {
        $sets = $match[1];
        $reps = preg_replace('/\s+/u', '', $match[2]) . 's';
    } elseif (preg_match('/\b(\d+)\s*(?:serie|sets?)\s*(?:da|x|per)\s*(\d+(?:\s*\/\s*\d+)?)\b/iu', $original, $match)) {
        $sets = $match[1];
        $reps = preg_replace('/\s+/u', '', $match[2]) ?? $match[2];
    } elseif (preg_match('/\b(\d+)\s*[xX]\s*(\d+(?:\s*\/\s*\d+)?)\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu', $original, $match)) {
        $sets = $match[1];
        $reps = preg_replace('/\s+/u', '', $match[2]) . 's';
    } elseif (preg_match('/\b(\d+)\s*[xX]\s*(\d+(?:\s*\/\s*\d+)?)\b/u', $original, $match, PREG_OFFSET_CAPTURE)) {
        $sets = $match[1][0];
        $reps = preg_replace('/\s+/u', '', $match[2][0]) ?? $match[2][0];
        $seriesMatchStart = $match[0][1];
        $seriesMatchEnd = $match[0][1] + strlen($match[0][0]);
        $afterTrimmed = ltrim(substr($original, $seriesMatchEnd));
        if (preg_match('/^(?:s|sec|secondi)\b/i', $afterTrimmed)) {
            $reps .= 's';
        }
    }

    if ($sets === '' && $reps === '' && preg_match('/^(\d+)\s+([\p{L}][\p{L}\p{N}\s\'-]{0,100}?)(?:\s+[xX]\s*(\d+))?\s*$/u', $original, $match)) {
        $reps = $match[1];
        $nameOverride = trim($match[2]);
        if (isset($match[3]) && $match[3] !== '') {
            $sets = $match[3];
        }
    }

    if ($reps === '' && preg_match('/\b(\d+)\s+al\s+minuto\b/iu', $original, $match)) {
        $reps = $match[1];
    }

    if ($sets === '' && preg_match('/\b[xX]\s*(max|\d+)\s*(?:minuti|min|m)?\b/iu', $original, $match)) {
        $sets = ucfirst(strtolower($match[1]));
    }

    if ($sets === '' && preg_match('/\b(\d+)\s*(?:serie|sets?)\b/iu', $original, $match)) {
        $sets = $match[1];
    }

    if ($reps === '' && preg_match('/\b(\d+)\s*(?:ripetizioni|reps?|rep)\b/iu', $original, $match)) {
        $reps = $match[1];
    }

    $weightedSchemes = workout_parser_extract_weighted_schemes($original);

    if ($seriesMatchEnd !== null && preg_match('/(?:\b|@)(\d+(?:[,.]\d+)?)\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu', substr($original, $seriesMatchEnd), $match)) {
        $weight = str_replace(',', '.', $match[1]) . 'kg';
    } elseif (count($weightedSchemes) <= 1 && preg_match('/(?:\b|@)(\d+(?:[,.]\d+)?)\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu', $original, $match)) {
        $weight = str_replace(',', '.', $match[1]) . 'kg';
    }

    $noteSchemes = [];
    if ($seriesMatchStart !== null) {
        $noteSchemes = workout_parser_extract_weighted_schemes(substr($original, 0, $seriesMatchStart));
    }
    if (!$noteSchemes && count($weightedSchemes) > 1) {
        $noteSchemes = array_filter($weightedSchemes, static function (string $scheme) use ($weight): bool {
            return $weight === '' || !str_contains($scheme, $weight);
        });
    }
    if ($noteSchemes) {
        $noteRows = workout_parser_format_weighted_note_rows($noteSchemes);
        if ($sets !== '' && $reps !== '' && $weight !== '') {
            $noteRows[] = 'Serie: ' . $sets . '  Rep: ' . $reps . '  Peso: ' . $weight;
        }
        $notes = implode("\n", array_values(array_unique($noteRows)));
    }

    if (preg_match('/\b(?:recupero|rest|rec)\s*(?:di\s*)?(\d+)\s*(?:\'|m|min|minuti)\s*(\d{1,2})\s*(?:"|s|sec|secondi)?(?=\s|$|[,.;:])/iu', $original, $match)) {
        $rest = $match[1] . "'" . str_pad($match[2], 2, '0', STR_PAD_LEFT) . '"';
    } elseif (preg_match('/\b(?:recupero|rest|rec)\s*(?:di\s*)?(\d+)\s*(' . WORKOUT_PARSER_TIME_UNIT . ')\b/iu', $original, $match)) {
        $rest = workout_parser_normalize_time_value($match[1], $match[2]);
    }

    $name = $nameOverride ?? workout_parser_extract_exercise_name($original);
    if ($weight === '' && $reps !== '' && !str_contains($reps, '/') && !str_ends_with($reps, 's') && workout_parser_looks_like_isometric_exercise($name)) {
        $reps .= 's';
    }

    if (!workout_parser_has_recognized_data($sets, $reps, $weight, $rest, $notes)) {
        if (!workout_parser_looks_like_standalone_exercise($name)) {
            return null;
        }
    }

    return [
        'type' => 'exercise',
        'name' => $name,
        'sets' => $sets,
        'reps' => $reps,
        'weight' => $weight,
        'rest' => $rest,
        'notes' => $notes,
    ];
}

function workout_parser_has_recognized_data(string $sets, string $reps, string $weight, string $rest, string $notes): bool
{
    return $sets !== '' || $reps !== '' || $weight !== '' || $rest !== '' || $notes !== '';
}

function workout_parser_looks_like_standalone_exercise(string $name): bool
{
    if ($name === '') {
        return false;
    }

    if (preg_match('/\b(?:ciao|coach|scheda|oggi|domani|riesco|mando|appena|allenamento|grazie)\b/iu', $name)) {
        return false;
    }

    if (preg_match('/^\s*solo\s+blocchi\s*$/iu', $name)) {
        return false;
    }

    $words = preg_split('/\s+/u', $name) ?: [];
    return count($words) <= 5 && (bool) preg_match('/^[\p{L}\p{N}\s\'-]+$/u', $name);
}

function workout_parser_looks_like_isometric_exercise(string $name): bool
{
    return (bool) preg_match('/\b(?:lever|plank|hold|isometr|tuck|l-?sit|anelli)\b/iu', $name);
}

function workout_parser_extract_weighted_schemes(string $line): array
{
    preg_match_all('/\b(?:max|\d+)\s+(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu', $line, $matches);
    return array_map(static fn (string $value): string => clean_string($value, 80), $matches[0] ?? []);
}

function workout_parser_format_weighted_note_rows(array $schemes): array
{
    $rows = [];

    foreach ($schemes as $scheme) {
        if (!preg_match('/\b(max|\d+)\s+(?:con|@)\s*(\d+(?:[,.]\d+)?)\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu', $scheme, $match)) {
            continue;
        }
        $value = ucfirst(strtolower($match[1]));
        $weight = str_replace(',', '.', $match[2]) . 'kg';
        $rows[] = 'Serie: ' . $value . '  Rep: ' . $value . '  Peso: ' . $weight;
    }

    return $rows;
}

function workout_parser_parse_circuit_items(string $items): array
{
    $rows = array_filter(array_map(
        static fn (string $item): string => clean_string($item, 120),
        preg_split('/\s*\|\s*/u', $items) ?: []
    ));

    $parsed = [];

    foreach ($rows as $row) {
        if (preg_match('/^(\d+)\s*(km|m|metri|metro)\s+(?:di\s+)?(.+)$/iu', $row, $match)) {
            $name = clean_string($match[3], 160);
            if ($name === '') {
                continue;
            }

            $unit = strtolower($match[2]);
            $unit = in_array($unit, ['metri', 'metro'], true) ? 'm' : $unit;
            $parsed[] = [
                'name' => $name,
                'reps' => $match[1] . ' ' . $unit,
            ];
            continue;
        }

        if (!preg_match('/^(\d+)\s+(.+)$/u', $row, $match)) {
            continue;
        }

        $name = clean_string($match[2], 160);
        if ($name === '') {
            continue;
        }

        $parsed[] = [
            'name' => $name,
            'reps' => (int) $match[1],
        ];
    }

    return $parsed;
}

function workout_parser_extract_exercise_name(string $line): string
{
    $patterns = [
        '/\b\d+\s*(?:s|sec|secondi)\s*(?:per|x)\s*\d+\s*(?:serie|sets?)\b/iu',
        '/\b\d+\s*(?:serie|sets?)\s*(?:da|x|per)\s*\d+(?:\s*\/\s*\d+)?\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu',
        '/\b\d+\s*(?:serie|sets?)\s*(?:da|x|per)\s*\d+(?:\s*\/\s*\d+)?\b/iu',
        '/\b\d+\s*[xX]\s*\d+(?:\s*\/\s*\d+)?\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu',
        '/\b\d+\s*[xX]\s*\d+(?:\s*\/\s*\d+)?\b/u',
        '/\b\d+\s+al\s+minuto\b/iu',
        '/\b[xX]\s*(?:max|\d+)\s*(?:minuti|min|m)?\b/iu',
        '/\b\d+\s*(?:serie|sets?)\b/iu',
        '/\b\d+\s*(?:ripetizioni|reps?|rep)\b/iu',
        '/\b(?:max|\d+)\s+(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu',
        '/(?:\bcon\s*(?:cavigliere|cavigliera)?\s*|@)?\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '\b/iu',
        '/\b(?:recupero|rest|rec)\s*(?:di\s*)?\d+\s*(?:\'|m|min|minuti)\s*\d{1,2}\s*(?:"|s|sec|secondi)?(?=\s|$|[,.;:])/iu',
        '/\b(?:recupero|rest|rec)\s*(?:di\s*)?\d+\s*' . WORKOUT_PARSER_TIME_UNIT . '\b/iu',
    ];

    $name = preg_replace($patterns, ' ', $line) ?? '';
    $name = preg_replace('/\b(?:con|per|da|di)\b/iu', ' ', $name) ?? '';
    $name = preg_replace('/\s+/u', ' ', $name) ?? '';
    return trim($name, " \t\n\r\0\x0B-:.");
}

function workout_parser_normalize_time_value(string $value, string $unit): string
{
    $unit = strtolower($unit);
    if (in_array($unit, ['min', 'm', 'minuti', '\''], true)) {
        return $value . "'";
    }
    return $value . 's';
}
