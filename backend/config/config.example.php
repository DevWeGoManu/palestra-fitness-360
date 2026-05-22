<?php
return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'nome_database',
    'db_user' => getenv('DB_USER') ?: 'utente_database',
    'db_pass' => getenv('DB_PASSWORD') ?: 'password_database',
    'api_url' => getenv('API_URL') ?: 'https://www.tuodominio.it/api',
    'app_url' => getenv('APP_URL') ?: 'https://www.tuodominio.it',
    'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: 'https://www.tuodominio.it'))),
    'session_ttl' => (int) (getenv('SESSION_TTL') ?: 3600),
    'mail_from' => getenv('MAIL_FROM') ?: 'info@tuodominio.it',
    'mail_from_name' => getenv('MAIL_FROM_NAME') ?: 'Palestra Fitness 360',
    'admin_notify_email' => getenv('ADMIN_NOTIFY_EMAIL') ?: 'admin@tuodominio.it',
];
