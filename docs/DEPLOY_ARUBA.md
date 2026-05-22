# Deploy Aruba Linux

## Prerequisiti

- Hosting Linux Aruba con PHP 8.
- Database MySQL Aruba.
- Accesso FTP/SFTP o File Manager.
- Dominio HTTPS attivo.

## Build frontend

```bash
cd frontend
npm install
npm run build
```

Caricare il contenuto di `frontend/dist/` nella root pubblica.

## Backend

Caricare:

- `backend/api/` come `/api/`
- `backend/config/` come `/config/`
- `backend/lib/` come `/lib/`

Creare la cartella log se possibile:

```text
/storage/logs
```

Se la cartella non e scrivibile, il backend usa `error_log()`.

## Database

Importare:

1. `database/schema.sql`
2. opzionale `database/seed.sql`

Per database esistenti applicare in ordine:

1. `database/migrations_002_mvp.sql`
2. `database/migrations_003_auth_flows.sql`

## Configurazione

Aggiornare `/config/config.php`:

```php
return [
    'db_host' => 'host_mysql_aruba',
    'db_name' => 'nome_database',
    'db_user' => 'utente_database',
    'db_pass' => 'password_database',
    'api_url' => 'https://www.tuodominio.it/api',
    'app_url' => 'https://www.tuodominio.it',
    'allowed_origins' => ['https://www.tuodominio.it'],
    'session_ttl' => 3600,
    'mail_from' => 'info@tuodominio.it',
    'mail_from_name' => 'Palestra Fitness 360',
    'admin_notify_email' => 'admin@tuodominio.it',
];
```

## Email Aruba

Il progetto invia:

- verifica email dopo registrazione autonoma;
- link reset password;
- notifica admin per nuovo utente registrato.

Il servizio email usa `mail()` PHP per restare compatibile con hosting Linux tradizionale Aruba. Verifiche consigliate:

- usare un mittente del dominio reale, ad esempio `info@tuodominio.it`;
- configurare SPF/DKIM/DMARC dal pannello Aruba se disponibili;
- controllare spam e limiti orari del provider;
- verificare che `storage/logs` sia scrivibile o leggere i log PHP Aruba.

La struttura e pronta per un futuro provider SMTP, ma non richiede librerie esterne.

## .htaccess

`frontend/public/.htaccess` viene copiato nella build e gestisce:

- fallback SPA;
- security headers;
- cache assets;
- gzip se `mod_deflate` e attivo.

## Permessi

- File PHP: lettura web server.
- `config.php`: non pubblicare credenziali in repository pubblico.
- `storage/logs`: scrivibile solo se si vuole logging file-based.

## HTTPS

In produzione usare solo HTTPS. I cookie sessione diventano `secure` automaticamente quando la richiesta e HTTPS.

## Troubleshooting

- Errore CORS: controllare `allowed_origins`.
- Email non ricevute: controllare mittente, spam, limiti `mail()` Aruba e log `storage/logs/mail.log`.
- Login non persiste: verificare HTTPS, cookie e dominio unico.
- API 500: controllare log PHP Aruba e `storage/logs/app.log`.
- Refresh pagina bianca: verificare `.htaccess` nella root.
- CSS/JS vecchi: svuotare cache browser o cambiare build.
