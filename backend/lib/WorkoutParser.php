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

    foreach ($parts as $part) {
        $part = workout_parser_strip_leading_marker(clean_string($part, 500));
        if ($part === '') {
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
        '/^(?:\d+\s*[xX]\s*\d+|(?:max|\d+)\s+(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '|(?:con|@)\s*\d+(?:[,.]\d+)?\s*' . WORKOUT_PARSER_WEIGHT_UNIT . '|recupero\b|rest\b)/iu',
        $part
    );
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

    $name = workout_parser_extract_exercise_name($original);
    if ($weight === '' && $reps !== '' && !str_contains($reps, '/') && !str_ends_with($reps, 's') && workout_parser_looks_like_isometric_exercise($name)) {
        $reps .= 's';
    }

    if (!workout_parser_has_recognized_data($sets, $reps, $weight, $rest, $notes)) {
        if (!workout_parser_looks_like_standalone_exercise($name)) {
            return null;
        }
    }

    return [
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

function workout_parser_extract_exercise_name(string $line): string
{
    $patterns = [
        '/\b\d+\s*(?:s|sec|secondi)\s*(?:per|x)\s*\d+\s*(?:serie|sets?)\b/iu',
        '/\b\d+\s*(?:serie|sets?)\s*(?:da|x|per)\s*\d+(?:\s*\/\s*\d+)?\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu',
        '/\b\d+\s*(?:serie|sets?)\s*(?:da|x|per)\s*\d+(?:\s*\/\s*\d+)?\b/iu',
        '/\b\d+\s*[xX]\s*\d+(?:\s*\/\s*\d+)?\s*(?:s|sec|secondi|[^\p{L}\p{N}\s])(?=\s|$|[,.;:])/iu',
        '/\b\d+\s*[xX]\s*\d+(?:\s*\/\s*\d+)?\b/u',
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
