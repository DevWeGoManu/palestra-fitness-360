-- Performance indexes for dashboard and common user/workout lookups.
-- Safe to run more than once: each index is created only if missing.

SET @schema_name = DATABASE();

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'users'
      AND index_name = 'idx_users_role'
);
SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_users_role ON users(role)',
    'SELECT "idx_users_role already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'workout_plans'
      AND index_name = 'idx_workout_plans_assigned_user'
);
SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_workout_plans_assigned_user ON workout_plans(assigned_user_id)',
    'SELECT "idx_workout_plans_assigned_user already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'workout_sessions'
      AND index_name = 'idx_workout_sessions_completed_at'
);
SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_workout_sessions_completed_at ON workout_sessions(completed_at)',
    'SELECT "idx_workout_sessions_completed_at already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
