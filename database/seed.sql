-- Password demo per tutti: hash bcrypt preconfigurato.
-- Cambiala subito dopo il primo accesso o crea un nuovo admin con password_hash().

INSERT INTO users (full_name, email, password_hash, role, status, email_verified_at) VALUES
('Admin Test', 'admin@test.it', '$2y$10$AymVvPAll09Oxzd5q39kcuEK5jT52QKKjJgu1OuNyDqYacEeFpPHq', 'admin', 'active', CURRENT_TIMESTAMP),
('Marco', 'coach@test.it', '$2y$10$AymVvPAll09Oxzd5q39kcuEK5jT52QKKjJgu1OuNyDqYacEeFpPHq', 'istruttore', 'active', CURRENT_TIMESTAMP),
('Emanuele', 'test@test.it', '$2y$10$AymVvPAll09Oxzd5q39kcuEK5jT52QKKjJgu1OuNyDqYacEeFpPHq', 'atleta', 'active', CURRENT_TIMESTAMP);
