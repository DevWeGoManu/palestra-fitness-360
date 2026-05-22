# Checklist Produzione

## Pre-release

- `docker compose up -d --build --remove-orphans`
- PHP lint OK.
- `npm run build` OK.
- Smoke test API OK.
- Test auth flows OK: registrazione, verifica email, approvazione, reset password.
- Test CSRF/header OK.
- Backup database creato.
- Credenziali demo rimosse o password cambiate.

## Sicurezza

- HTTPS attivo.
- `allowed_origins` limitato al dominio reale.
- `config.php` con credenziali reali e non pubblicato altrove.
- Password admin forte.
- Directory listing disabilitato.
- Header sicurezza verificati.
- CSRF verificato.
- Rate limit login verificato.
- Rate limit registrazione e reset password verificato.
- Token verifica email e reset password salvati solo come hash.
- Account registrati autonomamente creati come `atleta` e `pending`.
- Email reset password con risposta generica, senza rivelare se l'email esiste.
- Config email produzione impostata: `MAIL_FROM`, `MAIL_FROM_NAME`, `ADMIN_NOTIFY_EMAIL`.

## Deploy Aruba

- `frontend/dist` caricato in root.
- `/api`, `/config`, `/lib` caricati.
- `.htaccess` presente in root.
- Schema/migrazioni database applicati.
- `migrations_003_auth_flows.sql` applicata sui database esistenti.
- `config.php` aggiornato.
- Invio email verifica/reset provato da dominio reale.
- Login admin verificato.
- Creazione utente test verificata.
- Registrazione autonoma, verifica email e approvazione account verificate.
- Reset password via email verificato.
- Stampa/PDF scheda verificata.

## Backup

- Backup SQL pre-deploy.
- Backup file `config.php`.
- Restore testato almeno in locale.
- Procedura recovery documentata.
