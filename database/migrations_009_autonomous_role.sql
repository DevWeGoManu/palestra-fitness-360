-- Add an autonomous athlete role.
-- Users with role "autonomo" can create and edit only their own workout plans.

ALTER TABLE users
    MODIFY role ENUM('admin', 'istruttore', 'atleta', 'autonomo') NOT NULL DEFAULT 'atleta';
