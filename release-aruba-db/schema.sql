CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'istruttore', 'atleta', 'autonomo') NOT NULL DEFAULT 'atleta',
    status ENUM('pending', 'active', 'disabled') NOT NULL DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    assigned_user_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workout_plans_assigned_user (assigned_user_id),
    CONSTRAINT fk_workout_plans_assigned_user
        FOREIGN KEY (assigned_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_workout_plans_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id INT UNSIGNED NOT NULL,
    day_number TINYINT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    UNIQUE KEY uniq_plan_day (workout_plan_id, day_number),
    CONSTRAINT fk_workout_days_plan
        FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_day_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    sets VARCHAR(40) NULL,
    reps VARCHAR(40) NULL,
    weight VARCHAR(40) NULL,
    rest VARCHAR(40) NULL,
    notes TEXT NULL,
    order_index INT UNSIGNED NOT NULL DEFAULT 1,
    INDEX idx_exercises_day_order (workout_day_id, order_index),
    CONSTRAINT fk_exercises_day
        FOREIGN KEY (workout_day_id) REFERENCES workout_days(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id INT UNSIGNED NOT NULL,
    workout_day_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workout_sessions_completed_at (completed_at),
    INDEX idx_workout_sessions_day (workout_day_id),
    INDEX idx_workout_sessions_user_date (user_id, completed_at),
    INDEX idx_workout_sessions_plan_date (workout_plan_id, completed_at),
    INDEX idx_workout_sessions_user_plan_date (user_id, workout_plan_id, completed_at),
    CONSTRAINT fk_workout_sessions_plan
        FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_workout_sessions_day
        FOREIGN KEY (workout_day_id) REFERENCES workout_days(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_workout_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verification_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email_verification_token_hash (token_hash),
    INDEX idx_email_verification_user (user_id, used_at, expires_at),
    CONSTRAINT fk_email_verification_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(64) NULL,
    UNIQUE KEY uniq_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_user (user_id, used_at, expires_at),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_exercise_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    note TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_workout_exercise_note (exercise_id, user_id),
    INDEX idx_workout_exercise_notes_user (user_id),
    CONSTRAINT fk_workout_exercise_notes_exercise
        FOREIGN KEY (exercise_id) REFERENCES exercises(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_workout_exercise_notes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
