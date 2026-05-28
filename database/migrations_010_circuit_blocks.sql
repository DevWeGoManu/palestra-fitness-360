ALTER TABLE exercises
  ADD COLUMN block_type ENUM('exercise', 'circuit') NOT NULL DEFAULT 'exercise' AFTER workout_day_id,
  ADD COLUMN circuit_rounds VARCHAR(40) NULL AFTER notes,
  ADD COLUMN circuit_exercises JSON NULL AFTER circuit_rounds;
