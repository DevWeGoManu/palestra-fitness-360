<?php

require_once __DIR__ . '/../lib/Validation.php';
require_once __DIR__ . '/../lib/WorkoutParser.php';

$failures = [];

function parser_test_assert_equals(mixed $expected, mixed $actual, string $label): void
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

function parser_test_first_exercise(string $input): array
{
    [$days] = workout_parser_parse_text($input);
    return $days[0]['exercises'][0] ?? [];
}

function parser_test_exercises(string $input): array
{
    [$days] = workout_parser_parse_text($input);
    return $days[0]['exercises'] ?? [];
}

[$days] = workout_parser_parse_text('');
parser_test_assert_equals([], $days, 'empty input returns no days');

[$days] = workout_parser_parse_text('ciao coach, oggi facciamo una scheda nuova appena riesco');
parser_test_assert_equals([], $days, 'no exercise detected returns no days');

parser_test_assert_equals(6000, WORKOUT_PARSER_MAX_INPUT_LENGTH, 'max parser input length');

$longInput = 'Day1: Stacco 3x5 con 100kg ' . str_repeat('x', WORKOUT_PARSER_MAX_INPUT_LENGTH);
parser_test_assert_equals(true, strlen($longInput) > WORKOUT_PARSER_MAX_INPUT_LENGTH, 'long input fixture exceeds max length');

$exercise = parser_test_first_exercise('Day1: Stacco 3x5 con 100kg');
parser_test_assert_equals('Stacco', $exercise['name'] ?? null, '3x5 name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, '3x5 sets');
parser_test_assert_equals('5', $exercise['reps'] ?? null, '3x5 reps');
parser_test_assert_equals('100kg', $exercise['weight'] ?? null, '3x5 weight');

$exercise = parser_test_first_exercise('Giorno 1: front lever 3s per 3 serie');
parser_test_assert_equals('front lever', $exercise['name'] ?? null, 'seconds per series name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, 'seconds per series sets');
parser_test_assert_equals('3s', $exercise['reps'] ?? null, 'seconds per series reps');

$exercise = parser_test_first_exercise("giorno 1: Anelli front lever in full 3x6”");
parser_test_assert_equals('Anelli front lever in full', $exercise['name'] ?? null, 'smart quote name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, 'smart quote sets');
parser_test_assert_equals('6s', $exercise['reps'] ?? null, 'smart quote reps');

$exercise = parser_test_first_exercise('DAY 2: panca 4x8 recupero 90s');
parser_test_assert_equals('panca', $exercise['name'] ?? null, 'upper day name');
parser_test_assert_equals('4', $exercise['sets'] ?? null, 'upper day sets');
parser_test_assert_equals('8', $exercise['reps'] ?? null, 'upper day reps');
parser_test_assert_equals('90s', $exercise['rest'] ?? null, 'upper day rest');

$exercise = parser_test_first_exercise("Day1: Panca piana 3 serie da 8/10 recupero 1'30");
parser_test_assert_equals('Panca piana', $exercise['name'] ?? null, 'series da range name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, 'series da range sets');
parser_test_assert_equals('8/10', $exercise['reps'] ?? null, 'series da range reps');
parser_test_assert_equals('1\'30"', $exercise['rest'] ?? null, 'minute seconds rest');

$exercise = parser_test_first_exercise('Day1: Hollow body 4 serie per 30s recupero 2 minuti');
parser_test_assert_equals('Hollow body', $exercise['name'] ?? null, 'seconds with minutes name');
parser_test_assert_equals('4', $exercise['sets'] ?? null, 'seconds with minutes sets');
parser_test_assert_equals('30s', $exercise['reps'] ?? null, 'seconds with minutes reps');
parser_test_assert_equals('2\'', $exercise['rest'] ?? null, 'minutes rest');

$exercise = parser_test_first_exercise('Giorno 1: Trazioni zavorrate 3x5 con 25kg recupero 90s');
parser_test_assert_equals('Trazioni zavorrate', $exercise['name'] ?? null, 'italian chars name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, 'italian chars sets');
parser_test_assert_equals('5', $exercise['reps'] ?? null, 'italian chars reps');
parser_test_assert_equals('25kg', $exercise['weight'] ?? null, 'italian chars weight');
parser_test_assert_equals('90s', $exercise['rest'] ?? null, 'italian chars rest');

$exercises = parser_test_exercises("Day2:\n✅ Pull up\n3x5 con 25kg\n- Dip 3 x 12 @15kg\n• Curl martello 2x12 con 12kg");
parser_test_assert_equals(3, count($exercises), 'whatsapp exercises count');
parser_test_assert_equals('Pull up', $exercises[0]['name'] ?? null, 'whatsapp pull name');
parser_test_assert_equals('3', $exercises[0]['sets'] ?? null, 'whatsapp pull sets');
parser_test_assert_equals('5', $exercises[0]['reps'] ?? null, 'whatsapp pull reps');
parser_test_assert_equals('25kg', $exercises[0]['weight'] ?? null, 'whatsapp pull weight');
parser_test_assert_equals('Dip', $exercises[1]['name'] ?? null, 'whatsapp dip name');
parser_test_assert_equals('12', $exercises[1]['reps'] ?? null, 'whatsapp dip reps');
parser_test_assert_equals('15kg', $exercises[1]['weight'] ?? null, 'whatsapp dip weight');

$exercises = parser_test_exercises('Day2: Pull up,3x5 con 25kg, Dip 3x12 con 15kg');
parser_test_assert_equals(2, count($exercises), 'comma parameter merge count');
parser_test_assert_equals('Pull up', $exercises[0]['name'] ?? null, 'comma merge name');
parser_test_assert_equals('3', $exercises[0]['sets'] ?? null, 'comma merge sets');
parser_test_assert_equals('5', $exercises[0]['reps'] ?? null, 'comma merge reps');
parser_test_assert_equals('25kg', $exercises[0]['weight'] ?? null, 'comma merge weight');

$exercise = parser_test_first_exercise('Day1: Squat HB 1 con 120kg 2x6 con 90kg');
parser_test_assert_equals('Squat HB', $exercise['name'] ?? null, 'multi load name');
parser_test_assert_equals('2', $exercise['sets'] ?? null, 'multi load sets');
parser_test_assert_equals('6', $exercise['reps'] ?? null, 'multi load reps');
parser_test_assert_equals('90kg', $exercise['weight'] ?? null, 'multi load weight');
parser_test_assert_equals("Serie: 1  Rep: 1  Peso: 120kg\nSerie: 2  Rep: 6  Peso: 90kg", $exercise['notes'] ?? null, 'multi load notes');

$exercise = parser_test_first_exercise('Day2: Potenziamento croce alla lat machine 15 con 5kg 5 con 8kg Max con 12kg Max con 8kg');
parser_test_assert_equals('Potenziamento croce alla lat machine', $exercise['name'] ?? null, 'cluster name');
parser_test_assert_equals('', $exercise['sets'] ?? null, 'cluster sets empty');
parser_test_assert_equals('', $exercise['reps'] ?? null, 'cluster reps empty');
parser_test_assert_equals('', $exercise['weight'] ?? null, 'cluster weight empty');
parser_test_assert_equals("Serie: 15  Rep: 15  Peso: 5kg\nSerie: 5  Rep: 5  Peso: 8kg\nSerie: Max  Rep: Max  Peso: 12kg\nSerie: Max  Rep: Max  Peso: 8kg", $exercise['notes'] ?? null, 'cluster notes');

$exercise = parser_test_first_exercise('Giorno 3: Piegamenti stretti 3×10 rec 60s');
parser_test_assert_equals('Piegamenti stretti', $exercise['name'] ?? null, 'unicode multiply name');
parser_test_assert_equals('3', $exercise['sets'] ?? null, 'unicode multiply sets');
parser_test_assert_equals('10', $exercise['reps'] ?? null, 'unicode multiply reps');
parser_test_assert_equals('60s', $exercise['rest'] ?? null, 'short rec rest');

[$days] = workout_parser_parse_text("Day1:[17:42, 19/05/2026] Istruttore Palestra CRM: Anelli front  lever in full con elastico verde acqua\n3x6”\n[17:42, 19/05/2026] Istruttore Palestra CRM: Back Lever in adv tuck\n3x10”\n[17:42, 19/05/2026] Istruttore Palestra CRM: Stacco\n3x5 con 100kg\n[17:42, 19/05/2026] Istruttore Palestra CRM: Curl a martello\n2x12 con 12kg\n\nDay2: [17:05, 29/04/2026] Istruttore Palestra CRM: Stacco\n1 con 162.5kg\n2x4 con 130kg");
parser_test_assert_equals(2, count($days), 'whatsapp export day count');
parser_test_assert_equals(4, count($days[0]['exercises'] ?? []), 'whatsapp export day 1 exercise count');
parser_test_assert_equals('Anelli front lever in full elastico verde acqua', $days[0]['exercises'][0]['name'] ?? null, 'whatsapp export strips sender first name');
parser_test_assert_equals('Back Lever in adv tuck', $days[0]['exercises'][1]['name'] ?? null, 'whatsapp export strips sender second name');
parser_test_assert_equals('Stacco', $days[0]['exercises'][2]['name'] ?? null, 'whatsapp export strips sender stacco name');
parser_test_assert_equals('Curl a martello', $days[0]['exercises'][3]['name'] ?? null, 'whatsapp export strips sender curl name');
parser_test_assert_equals('Stacco', $days[1]['exercises'][0]['name'] ?? null, 'whatsapp export day 2 stacco name');
parser_test_assert_equals("Serie: 1  Rep: 1  Peso: 162.5kg\nSerie: 2  Rep: 4  Peso: 130kg", $days[1]['exercises'][0]['notes'] ?? null, 'whatsapp export day 2 stacco notes');

[$days] = workout_parser_parse_text("Day2:\n[17:12, 22/04/2026] Istruttore Palestra CRM: Verticale\n[17:12, 22/04/2026] Istruttore Palestra CRM: Front lever pull up agli anelli in  adv tuck\n3x6/8 con cavigliere 2kg\n[17:12, 22/04/2026] Istruttore Palestra CRM: HSPU al muro mani su paralleline rosse\n3x10\n[17:12, 22/04/2026] Istruttore Palestra CRM: Potenziamento planche a braccia tese\n3x12 con manubri 8kg");
parser_test_assert_equals(4, count($days[0]['exercises'] ?? []), 'whatsapp standalone day 2 exercise count');
parser_test_assert_equals('Verticale', $days[0]['exercises'][0]['name'] ?? null, 'whatsapp standalone vertical name');
parser_test_assert_equals('', $days[0]['exercises'][0]['sets'] ?? null, 'whatsapp standalone vertical sets empty');
parser_test_assert_equals('Front lever pull up agli anelli in adv tuck', $days[0]['exercises'][1]['name'] ?? null, 'whatsapp rep range name');
parser_test_assert_equals('3', $days[0]['exercises'][1]['sets'] ?? null, 'whatsapp rep range sets');
parser_test_assert_equals('6/8', $days[0]['exercises'][1]['reps'] ?? null, 'whatsapp rep range reps');
parser_test_assert_equals('2kg', $days[0]['exercises'][1]['weight'] ?? null, 'whatsapp rep range weight');
parser_test_assert_equals('HSPU al muro mani su paralleline rosse', $days[0]['exercises'][2]['name'] ?? null, 'whatsapp hspu name');
parser_test_assert_equals('Potenziamento planche a braccia tese manubri', $days[0]['exercises'][3]['name'] ?? null, 'whatsapp planche name');

[$days] = workout_parser_parse_text("Day1:\nSolo blocchi\n[08:59] Istruttore Palestra CRM: Front lever pull up agli anelli in tuck\n3x8/10\n[08:59] Istruttore Palestra CRM: HSPU al muro mani su paralleline rosse\n3x8");
parser_test_assert_equals(2, count($days[0]['exercises'] ?? []), 'mobile whatsapp no-date prefix count');
parser_test_assert_equals('Front lever pull up agli anelli in tuck', $days[0]['exercises'][0]['name'] ?? null, 'mobile whatsapp strips no-date prefix first');
parser_test_assert_equals('3', $days[0]['exercises'][0]['sets'] ?? null, 'mobile whatsapp rep range sets');
parser_test_assert_equals('8/10', $days[0]['exercises'][0]['reps'] ?? null, 'mobile whatsapp rep range reps');
parser_test_assert_equals('HSPU al muro mani su paralleline rosse', $days[0]['exercises'][1]['name'] ?? null, 'mobile whatsapp strips no-date prefix second');

[$days] = workout_parser_parse_text("[20/05, 17:59] Istruttore Palestra CRM: Verticale libera\nSolo blocchi\n[20/05, 17:59] Istruttore Palestra CRM: Front lever pull up agli anelli in  tuck\n3x8/10\n[20/05, 17:59] Istruttore Palestra CRM: HSPU al muro mani su paralleline rosse\n3x8");
parser_test_assert_equals(3, count($days[0]['exercises'] ?? []), 'mobile whatsapp date-first prefix count');
parser_test_assert_equals('Verticale libera', $days[0]['exercises'][0]['name'] ?? null, 'mobile whatsapp date-first vertical name');
parser_test_assert_equals('Front lever pull up agli anelli in tuck', $days[0]['exercises'][1]['name'] ?? null, 'mobile whatsapp date-first front name');
parser_test_assert_equals('8/10', $days[0]['exercises'][1]['reps'] ?? null, 'mobile whatsapp date-first rep range');
parser_test_assert_equals('HSPU al muro mani su paralleline rosse', $days[0]['exercises'][2]['name'] ?? null, 'mobile whatsapp date-first hspu name');

[$days] = workout_parser_parse_text("[17:42, 27/05/2026] Istruttore Palestra CRM: 1 Mu\n12 Bar Dip\n8 Pull Up\n\nx4\n[17:43, 27/05/2026] Istruttore Palestra CRM: Push up emom\n18 al minuto\nx Max minuti\n[17:43, 27/05/2026] Istruttore Palestra CRM: Squat HB\n3x10 con 60kg");
$exercises = $days[0]['exercises'] ?? [];
parser_test_assert_equals(3, count($exercises), 'whatsapp circuit emom count');
parser_test_assert_equals('circuit', $exercises[0]['type'] ?? null, 'whatsapp circuit block type');
parser_test_assert_equals('Circuito', $exercises[0]['name'] ?? null, 'whatsapp circuit block name');
parser_test_assert_equals(4, $exercises[0]['rounds'] ?? null, 'whatsapp circuit block rounds');
parser_test_assert_equals('', $exercises[0]['sets'] ?? null, 'whatsapp circuit block sets empty');
parser_test_assert_equals('', $exercises[0]['reps'] ?? null, 'whatsapp circuit block reps empty');
parser_test_assert_equals([
    ['name' => 'Mu', 'reps' => 1],
    ['name' => 'Bar Dip', 'reps' => 12],
    ['name' => 'Pull Up', 'reps' => 8],
], $exercises[0]['exercises'] ?? null, 'whatsapp circuit block exercises');
parser_test_assert_equals('', $exercises[0]['notes'] ?? null, 'whatsapp circuit block notes empty');
parser_test_assert_equals('exercise', $exercises[1]['type'] ?? null, 'whatsapp emom type');
parser_test_assert_equals('Push up emom', $exercises[1]['name'] ?? null, 'whatsapp emom name');
parser_test_assert_equals('Max', $exercises[1]['sets'] ?? null, 'whatsapp emom sets');
parser_test_assert_equals('18', $exercises[1]['reps'] ?? null, 'whatsapp emom reps');
parser_test_assert_equals('Squat HB', $exercises[2]['name'] ?? null, 'whatsapp squat name');
parser_test_assert_equals('3', $exercises[2]['sets'] ?? null, 'whatsapp squat sets');
parser_test_assert_equals('10', $exercises[2]['reps'] ?? null, 'whatsapp squat reps');
parser_test_assert_equals('60kg', $exercises[2]['weight'] ?? null, 'whatsapp squat weight');

if ($failures) {
    echo "WorkoutParserTest FAILED\n";
    foreach ($failures as $failure) {
        echo '- ' . $failure['label'] . "\n";
        echo '  expected: ' . var_export($failure['expected'], true) . "\n";
        echo '  actual:   ' . var_export($failure['actual'], true) . "\n";
    }
    exit(1);
}

echo "WorkoutParserTest OK\n";
