-- Athlete notes for individual exercises.

CREATE TABLE IF NOT EXISTS workout_exercise_notes (
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
