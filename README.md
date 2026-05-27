# AthleoDesk PWA

Web-app responsive/PWA per gestione palestra con frontend React + Vite, backend PHP 8 e database MySQL. Il progetto non usa servizi esterni come Supabase o Firebase ed e pensato per hosting Linux tradizionale, incluso Aruba.

## Struttura

- `frontend/`: app React, PWA manifest, service worker e UI responsive.
- `backend/`: API REST PHP con sessione, ruoli e query preparate PDO.
- `database/schema.sql`: schema MySQL.
- `database/seed.sql`: utenti demo.
- `docker-compose.yml`: ambiente development con frontend React, backend PHP 8 e MySQL.
- `docker-compose.prod.yml`: ambiente produzione locale con Nginx, PHP-FPM e MySQL.
- `docs/PROJECT_SPEC.md`: documento tecnico completo per rigenerare il progetto da zero.
- `docs/API.md`: documentazione endpoint e CSRF.
- `docs/DEPLOY_ARUBA.md`: guida deploy Aruba.
- `docs/RECOVERY.md`: backup e restore.
- `docs/PRODUCTION_CHECKLISTS.md`: checklist pre-release, sicurezza e backup.
- `docs/PRODUCTION_HARDENING_REPORT.md`: report tecnico finale hardening.
- `docs/STAGING_DEPLOY.md`: guida primo deploy staging Aruba.
- `docs/MOBILE_MANUAL_CHECKLIST.md`: checklist manuale Android/iPhone.
- `docs/RELEASE_NOTES_v1.0.0.md`: note release v1.0.0.

## Utenti demo

Dopo aver importato schema e seed:

- `admin@palestra.local`
- `coach@palestra.local`
- `atleta@palestra.local`

Password demo per tutti: `password`. Cambiala subito o crea utenti reali dall'interfaccia.

## Sviluppo locale con Docker

1. Crea il file `.env` partendo da `.env.example`:

```bash
cp .env.example .env
```

Su Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

2. Avvia l'ambiente development:

```bash
docker compose up
```

3. Apri:

- frontend Vite: `http://localhost:5173`
- backend PHP/API: `http://localhost:8000`
- API login: `http://localhost:8000/api/auth/login.php`
- MySQL interno Docker: `mysql:3306`

Il container MySQL importa automaticamente `database/schema.sql` e `database/seed.sql` al primo avvio del volume.

### Comandi utili Docker

Rebuild container:

```bash
docker compose up --build
```

Avvio in background:

```bash
docker compose up -d
```

Log:

```bash
docker compose logs -f
```

Reset completo database, con reimport automatico di schema e seed:

```bash
docker compose down -v
docker compose up --build
```

Test connessione MySQL:

```bash
docker compose exec mysql mysql -u gym_user -pgym_password gym_app -e "SHOW TABLES;"
```

Lint PHP:

```bash
docker compose exec backend sh -c "find /var/www/backend -name '*.php' -print -exec php -l {} \;"
```

Smoke test API con login, ruoli, CRUD utenti, CRUD programmi e sessioni:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/test-api.ps1
```

Test sicurezza CSRF/header:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/test-security.ps1
```

Test registrazione autonoma, verifica email, approvazione account e reset password:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/test-auth-flows.ps1
```

Stress test rate limit login:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/stress-login.ps1
```

Backup database Docker:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup-db.ps1
```

Build frontend produzione:

```bash
docker compose run --rm frontend npm run build
```

Ambiente produzione locale con Nginx, dopo la build frontend:

```bash
docker compose -f docker-compose.prod.yml up --build
```

Apri `http://localhost:8080`. In questo profilo Nginx serve `frontend/dist` e inoltra `/api/*.php` a PHP-FPM.

## Sviluppo locale senza Docker

1. Importa `database/schema.sql` e poi `database/seed.sql` in MySQL.
2. Copia o modifica `backend/config/config.php` con i dati del database locale.
3. Se ti serve un hash per un admin iniziale reale, usa:

```bash
php backend/tools/hash-password.php 'PasswordSicura123!'
```

4. Avvia un server PHP puntato alla cartella backend:

```bash
php -S localhost:8000 -t backend
```

5. Crea `frontend/.env`:

```env
VITE_API_BASE=http://localhost:8000/api
```

6. Installa e avvia il frontend:

```bash
cd frontend
npm install
npm run dev
```

## Deploy su Aruba Linux

1. Importa `database/schema.sql` dal pannello MySQL Aruba.
2. Importa `database/seed.sql` solo se vuoi gli utenti demo.
3. Modifica `backend/config/config.php` con host, nome database, utente e password forniti da Aruba, oppure imposta le variabili ambiente equivalenti se disponibili.
4. In `backend/config/config.php`, imposta `app_url`, `api_url` e `allowed_origins` con il dominio pubblico, ad esempio `https://www.tuodominio.it`.
   Configura anche l'invio email:

```php
'mail_from' => 'info@tuodominio.it',
'mail_from_name' => 'AthleoDesk',
'admin_notify_email' => 'admin@tuodominio.it',
```

Il backend usa `mail()` PHP come invio compatibile Aruba e scrive comunque una traccia in `storage/logs/mail.log` se la cartella e scrivibile. In produzione verifica che il mittente appartenga al dominio e che SPF/DKIM siano configurati dal pannello Aruba quando disponibili.
5. Nel frontend lascia `VITE_API_BASE=/api`, poi genera la build:

```bash
cd frontend
npm install
npm run build
```

6. Carica nel web root Aruba:

- contenuto di `frontend/dist/` nella root pubblica del sito;
- `backend/api/` come cartella `api/`;
- `backend/config/` come cartella `config/`;
- `backend/lib/` come cartella `lib/`;
- `frontend/dist/.htaccess` nella root pubblica, per evitare errori al refresh delle route React;
- facoltativo: `backend/.htaccess` nella root.

La struttura online risultante sara simile a:

```text
/index.html
/assets/...
/manifest.json
/service-worker.js
/.htaccess
/icons/icon.svg
/api/auth/login.php
/api/users/index.php
/api/workouts/index.php
/config/config.php
/lib/Database.php
```

## Deploy staging Aruba

Per il primo deploy reale usa preferibilmente un sottodominio, ad esempio:

```text
https://staging.tuodominio.it
```

Passi rapidi:

1. Genera build frontend:

```bash
cd frontend
npm install
npm run build
```

2. Importa su MySQL Aruba:

```text
database/schema.sql
database/seed.sql opzionale
```

3. Configura `backend/config/config.php` con dominio staging:

```php
'api_url' => 'https://staging.tuodominio.it/api',
'app_url' => 'https://staging.tuodominio.it',
'allowed_origins' => ['https://staging.tuodominio.it'],
'session_ttl' => 3600,
```

4. Carica:

- contenuto di `frontend/dist/` nella root del sottodominio;
- `backend/api/` come `/api/`;
- `backend/config/` come `/config/`;
- `backend/lib/` come `/lib/`.

5. Crea admin reale via CLI PHP, se disponibile:

```bash
php tools/create-admin.php "Nome Admin" admin@dominio.it "PasswordSicura123!"
```

In alternativa cambia password a un admin esistente:

```bash
php tools/change-admin-password.php admin@palestra.local "PasswordSicura123!"
```

6. Rimuovi utenti demo quando non servono piu:

```bash
php tools/remove-demo-users.php
```

Guida completa: `docs/STAGING_DEPLOY.md`.
Checklist mobile: `docs/MOBILE_MANUAL_CHECKLIST.md`.
Release notes: `docs/RELEASE_NOTES_v1.0.0.md`.

## Sicurezza implementata

- Password salvate con `password_hash()` e verificate con `password_verify()`.
- Registrazione autonoma con ruolo forzato `atleta`, account `pending`, verifica email e approvazione da admin/istruttore.
- Reset password con token monouso, scadenza 60 minuti e risposta generica per non rivelare email registrate.
- Sessione PHP con cookie `HttpOnly` e `SameSite=Lax`.
- Query SQL preparate con PDO.
- Errori API restituiti in JSON.
- CORS ristretto agli origin configurati in `ALLOWED_ORIGINS` o `backend/config/config.php`.
- Controlli permessi lato backend:
  - atleta: legge solo i propri programmi;
  - istruttore/admin: gestisce utenti e allenamenti;
  - solo admin puo creare altri admin.

## Variabili ambiente

- `DB_HOST`: host MySQL, in Docker `mysql`.
- `DB_NAME`: nome database.
- `DB_USER`: utente database.
- `DB_PASSWORD`: password database.
- `DB_ROOT_PASSWORD`: password root MySQL del container.
- `API_URL`: URL pubblico delle API.
- `APP_URL`: URL pubblico del frontend.
- `ALLOWED_ORIGINS`: lista origin separati da virgola per CORS.
- `VITE_API_BASE`: base API usata dal frontend, normalmente `/api`.
- `VITE_PROXY_TARGET`: target usato da Vite in Docker development, normalmente `http://backend:8000`.
- `MAIL_FROM`: indirizzo mittente per verifica email e reset password.
- `MAIL_FROM_NAME`: nome mittente.
- `ADMIN_NOTIFY_EMAIL`: email che riceve notifica di nuova registrazione.

## Endpoint principali

- `POST /api/auth/login.php`
- `POST /api/auth/register.php`
- `GET|POST /api/auth/verify-email.php`
- `POST /api/auth/request-password-reset.php`
- `POST /api/auth/reset-password.php`
- `GET /api/auth/me.php`
- `POST /api/auth/logout.php`
- `GET|POST /api/users/index.php`
- `GET /api/users/show.php?id=1`
- `GET|POST /api/workouts/index.php`
- `GET|PUT|DELETE /api/workouts/show.php?id=1`

## Note MVP

L'editor salva i giorni e gli esercizi come struttura completa: quando aggiorni un programma, gli esercizi dei giorni inviati vengono riscritti in modo semplice e prevedibile. Per un uso avanzato si possono aggiungere audit log, reset password, allegati, progressi atleta e storico carichi.

## Funzionalita MVP aggiunte

- CRUD utenti completo: creazione, modifica, eliminazione con conferma lato UI; eliminazione solo admin; istruttore bloccato sulla modifica degli admin.
- Libreria esercizi predefiniti con ricerca e filtro per gruppo muscolare.
- Storico allenamenti con salvataggio completamento e pagina `Storico`.
- Dashboard differenziata per atleta e istruttore/admin.
- Editor scheda migliorato: drag & drop esercizi, duplicazione esercizio, duplicazione day, salvataggio sticky, avviso uscita con modifiche non salvate.
- Export scheda tramite stampa browser ottimizzata CSS print, utilizzabile come PDF da dialog di stampa.
- Toast notification, loading states, rate limiting base login e validazioni backend piu rigorose.
