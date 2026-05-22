CREATE TABLE IF NOT EXISTS exercise_library (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    muscle_group ENUM('petto', 'schiena', 'gambe', 'spalle', 'braccia', 'addome', 'cardio') NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'base',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exercise_library_group_name (muscle_group, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workout_sessions_user_date (user_id, completed_at),
    INDEX idx_workout_sessions_plan_date (workout_plan_id, completed_at),
    CONSTRAINT fk_workout_sessions_plan
        FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_workout_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO exercise_library (name, muscle_group, category)
SELECT * FROM (
    SELECT 'Panca piana bilanciere', 'petto', 'forza' UNION ALL
    SELECT 'Chest press', 'petto', 'macchina' UNION ALL
    SELECT 'Croci manubri', 'petto', 'isolamento' UNION ALL
    SELECT 'Lat machine avanti', 'schiena', 'macchina' UNION ALL
    SELECT 'Rematore bilanciere', 'schiena', 'forza' UNION ALL
    SELECT 'Pulley basso', 'schiena', 'macchina' UNION ALL
    SELECT 'Squat', 'gambe', 'forza' UNION ALL
    SELECT 'Leg press', 'gambe', 'macchina' UNION ALL
    SELECT 'Affondi camminati', 'gambe', 'funzionale' UNION ALL
    SELECT 'Military press', 'spalle', 'forza' UNION ALL
    SELECT 'Alzate laterali', 'spalle', 'isolamento' UNION ALL
    SELECT 'Shoulder press', 'spalle', 'macchina' UNION ALL
    SELECT 'Curl bilanciere', 'braccia', 'isolamento' UNION ALL
    SELECT 'Pushdown cavo', 'braccia', 'isolamento' UNION ALL
    SELECT 'French press', 'braccia', 'isolamento' UNION ALL
    SELECT 'Plank', 'addome', 'core' UNION ALL
    SELECT 'Crunch', 'addome', 'corpo libero' UNION ALL
    SELECT 'Leg raise', 'addome', 'core' UNION ALL
    SELECT 'Tapis roulant', 'cardio', 'cardio' UNION ALL
    SELECT 'Bike', 'cardio', 'cardio' UNION ALL
    SELECT 'Ellittica', 'cardio', 'cardio'
) AS seed(name, muscle_group, category)
WHERE NOT EXISTS (SELECT 1 FROM exercise_library LIMIT 1);
