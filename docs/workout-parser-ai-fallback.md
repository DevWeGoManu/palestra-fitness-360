# Workout Parser AI Fallback

## Proposed Flow

1. Run the deterministic parser first.
2. Evaluate fallback confidence with `workout_parser_evaluate_ai_fallback_need()`.
3. Use AI only when the deterministic parser returns no exercises or misses advanced structures such as `circuito`, `superset`, `emom`, or `amrap`.
4. The AI provider must live in the backend. The frontend must never receive provider keys or call the provider directly.
5. The AI response must be sanitized with `workout_parser_sanitize_ai_response()` before returning preview data to the UI.
6. The UI must continue showing a preview and requiring user confirmation before applying anything to the current workout.

The current implementation includes only a disabled backend stub: `parseWorkoutWithAiFallback(text)`.

## Expected JSON Schema

```json
{
  "days": [
    {
      "day_number": 1,
      "title": "Day 1",
      "exercises": [
        {
          "type": "exercise",
          "name": "Squat HB",
          "sets": "3",
          "reps": "10",
          "weight": "60kg",
          "rest": "90s",
          "notes": ""
        },
        {
          "type": "circuit",
          "name": "Circuito",
          "rounds": 4,
          "rest": "1 minuto",
          "exercises": [
            { "name": "corsa", "reps": "1 km" },
            { "name": "push up", "reps": 10 },
            { "name": "squat", "reps": 10 }
          ]
        },
        {
          "type": "superset",
          "name": "Superset",
          "rounds": 3,
          "rest": "",
          "exercises": [
            { "name": "panca", "reps": 10 },
            { "name": "rematore", "reps": 10 }
          ],
          "notes": ""
        }
      ]
    }
  ]
}
```

## Example Inputs For Future AI Provider Tests

- `Fai un circuito per 4 giri con 1km corsa, 10 push up, 10 squat, recupero 1 minuto`
- `AMRAP 12 minuti: 5 pull up, 10 push up, 15 squat`
- `Superset 3 giri: panca 10 reps + rematore 10 reps`

## Sanitization Rules

- Unknown fields are discarded.
- Empty or invalid blocks are discarded.
- `day_number` is clamped to `1..7`.
- Text fields are length-limited with existing backend validation helpers.
- Circuit and superset nested exercises require both `name` and `reps` or `quantity`.
- Invalid AI JSON returns errors and must not be applied to a workout.
