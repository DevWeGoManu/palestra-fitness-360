-- Track completed workout day in each workout session.

SET @schema_name = DATABASE();

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'workout_sessions'
      AND column_name = 'workout_day_id'
);
SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE workout_sessions ADD COLUMN workout_day_id INT UNSIGNED NULL AFTER workout_plan_id',
    'SELECT "workout_day_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = @schema_name
      AND table_name = 'workout_sessions'
      AND constraint_name = 'fk_workout_sessions_day'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql = IF(
    @fk_exists = 0,
    'ALTER TABLE workout_sessions ADD CONSTRAINT fk_workout_sessions_day FOREIGN KEY (workout_day_id) REFERENCES workout_days(id) ON DELETE SET NULL',
    'SELECT "fk_workout_sessions_day already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'workout_sessions'
      AND index_name = 'idx_workout_sessions_day'
);
SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_workout_sessions_day ON workout_sessions(workout_day_id)',
    'SELECT "idx_workout_sessions_day already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'workout_sessions'
      AND index_name = 'idx_workout_sessions_user_plan_date'
);
SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_workout_sessions_user_plan_date ON workout_sessions(user_id, workout_plan_id, completed_at)',
    'SELECT "idx_workout_sessions_user_plan_date already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
