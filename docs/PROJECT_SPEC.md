# AthleoDesk PWA - Documento tecnico rigenerabile

Questo documento descrive in modo completo il progetto realizzato, con un livello di dettaglio sufficiente per permettere a un altro AI engineer di rigenerarlo da zero in una nuova codebase.

Il progetto e una web-app responsive/PWA per la gestione di una palestra. Usa frontend React + Vite, backend PHP 8, database MySQL, API REST, Docker Compose per development e un profilo separato con Nginx/PHP-FPM per produzione locale. Il deploy target resta un hosting Linux Aruba tradizionale, quindi il progetto non dipende da Supabase, Firebase o servizi serverless.

## 1. Architettura Progetto

### Visione generale

L'applicazione e composta da quattro layer principali:

- Frontend React/Vite: interfaccia utente responsive, PWA, routing hash-based e chiamate REST.
- Backend PHP 8: API REST con sessioni PHP, autorizzazione per ruoli, PDO MySQL e output JSON.
- Database MySQL: schema relazionale per utenti, programmi, giorni e esercizi.
- Ambiente Docker: development con frontend su `5173`, backend PHP su `8000` e MySQL interno; produzione locale separata con Nginx su `8080` e PHP-FPM.

Flusso development:

```text
Browser
  -> http://localhost:5173
  -> Vite dev server
  -> proxy /api verso http://backend:8000
  -> PHP built-in server
  -> PDO MySQL verso mysql:3306
```

Flusso produzione locale:

```text
Browser
  -> http://localhost:8080
  -> Nginx
  -> frontend/dist per asset statici
  -> /api/*.php verso PHP-FPM
  -> PDO MySQL verso mysql:3306
```

Flusso Aruba:

```text
Browser
  -> dominio Aruba
  -> file statici React buildati
  -> /api/*.php
  -> PHP 8 hosting Aruba
  -> MySQL Aruba
```

### Frontend

Il frontend vive nella cartella `frontend/` ed e una single page application con React.

Caratteristiche principali:

- React con componenti funzionali.
- Vite come bundler/dev server.
- Routing interno basato su `window.location.hash`, senza React Router.
- Chiamate API centralizzate in `frontend/src/api.js`.
- UI responsive con CSS custom in `frontend/src/styles.css`.
- Icone tramite `lucide-react`.
- PWA con `manifest.json`, service worker e icona SVG.

### Backend

Il backend vive nella cartella `backend/` ed e composto da file PHP semplici, uno per endpoint.

Caratteristiche principali:

- PHP 8.
- Sessioni PHP cookie-based.
- Cookie sessione `HttpOnly`, `SameSite=Lax`, `secure` quando la richiesta e HTTPS.
- PDO MySQL con prepared statements.
- CORS configurabile tramite `ALLOWED_ORIGINS`.
- Risposte JSON coerenti.
- Gestore globale di eccezioni in `backend/api/bootstrap.php`.
- File endpoint sotto `backend/api/`.
- Librerie condivise sotto `backend/lib/`.

### Database

Il database e MySQL/InnoDB. Lo schema e in `database/schema.sql` e il seed demo e in `database/seed.sql`.

Entita:

- `users`: utenti del sistema con ruolo.
- `workout_plans`: programmi allenamento assegnati a un atleta e creati da admin/istruttore.
- `workout_days`: Day 1 fino a Day 7 per ogni programma.
- `exercises`: esercizi contenuti nei day.

### Docker

Il progetto contiene due compose distinti:

- `docker-compose.yml`: development.
- `docker-compose.prod.yml`: produzione locale con Nginx.

Development espone solo:

- frontend React/Vite su `localhost:5173`;
- backend PHP su `localhost:8000`.

MySQL non e esposto sulla macchina host in development: e raggiungibile dagli altri container come `mysql:3306`.

### Nginx

Nginx non viene usato in development. E presente solo in `docker-compose.prod.yml`, dove:

- serve `frontend/dist` come document root;
- applica fallback SPA con `try_files $uri $uri/ /index.html`;
- inoltra `/api/*.php` a PHP-FPM;
- usa `docker/nginx/default.conf`.

### PWA

La PWA e composta da:

- `frontend/public/manifest.json`;
- `frontend/public/service-worker.js`;
- `frontend/public/icons/icon.svg`;
- registrazione service worker in `frontend/src/main.jsx`.

Il service worker implementa una cache base per app shell e asset statici. Le richieste `/api/` non vengono cacheate.

### Struttura cartelle

```text
.
|-- .env.example
|-- README.md
|-- docker-compose.yml
|-- docker-compose.prod.yml
|-- backend
|   |-- .htaccess
|   |-- api
|   |   |-- bootstrap.php
|   |   |-- auth
|   |   |   |-- login.php
|   |   |   |-- logout.php
|   |   |   `-- me.php
|   |   |-- users
|   |   |   |-- index.php
|   |   |   `-- show.php
|   |   `-- workouts
|   |       |-- index.php
|   |       `-- show.php
|   |-- config
|   |   |-- config.example.php
|   |   `-- config.php
|   |-- lib
|   |   |-- Auth.php
|   |   |-- Database.php
|   |   `-- Response.php
|   `-- tools
|       `-- hash-password.php
|-- database
|   |-- schema.sql
|   `-- seed.sql
|-- docker
|   |-- frontend.Dockerfile
|   |-- nginx
|   |   `-- default.conf
|   |-- php
|   |   `-- Dockerfile
|   `-- php-fpm
|       `-- Dockerfile
|-- docs
|   `-- PROJECT_SPEC.md
|-- frontend
|   |-- .env.example
|   |-- index.html
|   |-- package.json
|   |-- vite.config.js
|   |-- public
|   |   |-- .htaccess
|   |   |-- manifest.json
|   |   |-- service-worker.js
|   |   |-- icons
|   |   |   `-- icon.svg
|   |   `-- images
|   |       `-- gym-bg.svg
|   `-- src
|       |-- App.jsx
|       |-- api.js
|       |-- main.jsx
|       `-- styles.css
`-- scripts
    `-- test-api.ps1
```

## 2. Tecnologie Usate

### React + Vite

Il frontend usa React per componenti, stato locale e rendering condizionale. Vite fornisce:

- dev server su porta `5173`;
- proxy `/api` verso backend Docker;
- build ottimizzata in `frontend/dist`;
- copia dei file in `frontend/public` nella build finale.

### PHP 8

Il backend usa PHP 8 senza framework. Questa scelta mantiene il progetto caricabile su hosting Aruba Linux tradizionale.

In development Docker viene usata l'immagine `php:8.3-cli-alpine` con server built-in:

```bash
php -S 0.0.0.0:8000 -t /var/www/backend
```

In produzione locale viene usata `php:8.3-fpm-alpine` dietro Nginx.

### MySQL

Database relazionale MySQL 8.4 in Docker. Su Aruba si usa il database MySQL fornito dal pannello hosting.

### Docker Compose

Docker Compose gestisce:

- build immagini PHP e frontend;
- container MySQL;
- volumi persistenti;
- healthcheck MySQL;
- rete interna tra servizi.

### Nginx

Nginx e riservato al profilo `docker-compose.prod.yml`. Serve la build React e inoltra le API a PHP-FPM.

### API REST

Le API sono endpoint PHP che ricevono e restituiscono JSON. Non esiste un router centralizzato: ogni endpoint e un file PHP fisico.

### PWA

La web-app include manifest e service worker per comportamento installabile e caching base della app shell.

## 3. Funzionalita Implementate

### Login

La pagina login e implementata nel componente `Login` dentro `frontend/src/App.jsx`.

Campi:

- email;
- password.

Credenziali demo:

- `admin@palestra.local` / `password`;
- `coach@palestra.local` / `password`;
- `atleta@palestra.local` / `password`.

Endpoint:

```text
POST /api/auth/login.php
```

Il backend:

- legge JSON request body;
- normalizza email in lowercase;
- cerca utente per email;
- verifica password con `password_verify()`;
- rigenera ID sessione con `session_regenerate_id(true)`;
- salva `$_SESSION['user_id']`;
- restituisce l'utente senza `password_hash`.

### Autenticazione

L'autenticazione e session-based. Il frontend usa `fetch()` con:

```js
credentials: 'include'
```

All'avvio app chiama:

```text
GET /api/auth/me.php
```

Se la sessione e valida, riceve l'utente corrente e mostra la shell autenticata. Altrimenti mostra login.

### Gestione sessioni

Le sessioni sono PHP native:

- cookie `HttpOnly`;
- `SameSite=Lax`;
- `secure` attivo automaticamente quando la richiesta e HTTPS;
- logout con reset `$_SESSION`, cancellazione cookie sessione e `session_destroy()`.

### Ruoli

Ruoli applicativi:

- `admin`;
- `istruttore`;
- `atleta`.

Regole implementate:

- `admin`: puo gestire utenti e allenamenti.
- `istruttore`: puo gestire utenti e allenamenti, ma non creare altri admin.
- `atleta`: puo vedere solo i propri programmi assegnati.

Le regole sono controllate lato backend tramite `require_user()`, `require_role()` e `can_manage()` in `backend/lib/Auth.php`.

### Dashboard

La dashboard e la route hash `/`.

Per `admin` e `istruttore` mostra accessi rapidi a:

- Allenamenti;
- Utenti.

Per `atleta` mostra accesso ai propri allenamenti.

### Sidebar responsive

La shell applicativa include:

- sidebar desktop fissa;
- topbar con nome e ruolo utente;
- menu mobile apribile con icona `Menu`;
- overlay mobile chiudibile;
- pulsante logout.

La sidebar mostra `Utenti` solo a `admin` e `istruttore`.

### Utenti

Pagina hash:

```text
#/users
```

Accessibile solo a `admin` e `istruttore`.

Funzioni implementate:

- lista utenti;
- creazione utente;
- scelta ruolo;
- navigazione al dettaglio utente.

Il backend consente:

- `GET /api/users/index.php`;
- `POST /api/users/index.php`;
- `GET /api/users/show.php?id=...`.

Non e implementata la modifica o eliminazione utente nel frontend attuale. Il termine CRUD nel progetto operativo riguarda principalmente creazione e lettura utenti, mentre i programmi hanno creazione, lettura, aggiornamento ed eliminazione. Lo smoke test verifica creazione e lettura utenti.

### Programmi allenamento

Pagina hash:

```text
#/workouts
```

Per `atleta`:

- mostra solo programmi assegnati all'utente autenticato.

Per `admin` e `istruttore`:

- mostra tutti i programmi;
- permette creazione nuovo programma;
- richiede selezione atleta;
- crea automaticamente Day 1 fino a Day 7.

### Editor programma

Pagina hash:

```text
#/plan?id=ID
```

Per `admin` e `istruttore`:

- modifica nome programma;
- modifica atleta assegnato;
- modifica titoli Day;
- aggiunge esercizi;
- modifica esercizi;
- rimuove esercizi;
- salva l'intero programma.

Per `atleta`:

- visualizza programma e esercizi in sola lettura.

### Day 1-7

Quando un programma viene creato, il backend crea automaticamente sette record in `workout_days`:

- `day_number` da 1 a 7;
- `title` iniziale `Day 1`, `Day 2`, ecc.

Ogni day contiene zero o piu esercizi.

### Esercizi

Campi esercizio:

- `name`;
- `sets`;
- `reps`;
- `weight`;
- `rest`;
- `notes`;
- `order_index`.

### Assegnazione schede

Un programma ha:

- `assigned_user_id`: atleta assegnato;
- `created_by`: admin/istruttore che lo ha creato.

Il backend valida che `assigned_user_id` punti a un utente con ruolo `atleta`.

### Service worker

File:

```text
frontend/public/service-worker.js
```

Comportamento:

- cache `CACHE_NAME = 'gym-manager-v1'`;
- app shell iniziale:
  - `/`;
  - `/index.html`;
  - `/manifest.json`;
  - `/icons/icon.svg`;
- non intercetta API `/api/`;
- cache-first per asset statici.

### Manifest

File:

```text
frontend/public/manifest.json
```

Definisce:

- `name`: AthleoDesk;
- `short_name`: Gym;
- `display`: standalone;
- colori PWA;
- icona SVG maskable.

## 4. Database

### Schema SQL completo

Schema corrente:

```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'istruttore', 'atleta') NOT NULL DEFAULT 'atleta',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    assigned_user_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
```

### Relazioni

Relazioni principali:

- `users.id` -> `workout_plans.assigned_user_id`
  - un atleta puo avere molti programmi;
  - se l'atleta viene eliminato, i suoi programmi vengono eliminati con `ON DELETE CASCADE`.

- `users.id` -> `workout_plans.created_by`
  - un admin/istruttore puo creare molti programmi;
  - `ON DELETE RESTRICT` evita eliminazione del creatore se esistono programmi collegati.

- `workout_plans.id` -> `workout_days.workout_plan_id`
  - un programma ha sette day;
  - cancellando il programma, i day vengono cancellati con cascade.

- `workout_days.id` -> `exercises.workout_day_id`
  - un day ha molti esercizi;
  - cancellando un day, gli esercizi vengono cancellati con cascade.

### Vincoli

- `users.email` e unico.
- `users.role` e limitato a `admin`, `istruttore`, `atleta`.
- `workout_days` ha vincolo unico `(workout_plan_id, day_number)`.
- `exercises` ha indice `(workout_day_id, order_index)` per ordinamento efficiente.

### Permessi applicativi

I permessi non sono implementati a livello database, ma a livello API:

- Utente non autenticato: `401`.
- Ruolo non autorizzato: `403`.
- Atleta:
  - puo accedere solo ai programmi dove `workout_plans.assigned_user_id = current_user.id`;
  - non puo leggere utenti;
  - non puo creare/modificare/eliminare programmi.
- Admin/istruttore:
  - puo leggere utenti;
  - puo creare utenti;
  - puo leggere, creare, modificare ed eliminare programmi;
  - puo assegnare programmi solo ad utenti atleta.
- Istruttore:
  - non puo creare utenti con ruolo `admin`.

### Seed demo

File:

```text
database/seed.sql
```

Inserisce tre utenti:

```text
Admin Palestra       admin@palestra.local    admin
Istruttore Demo      coach@palestra.local    istruttore
Atleta Demo          atleta@palestra.local   atleta
```

Password demo:

```text
password
```

L'hash e bcrypt compatibile con `password_verify()`.

## 5. Backend API

### Bootstrap API

Ogni endpoint include:

```php
require_once __DIR__ . '/../bootstrap.php';
```

`backend/api/bootstrap.php` si occupa di:

- leggere config;
- configurare CORS;
- gestire preflight `OPTIONS`;
- configurare cookie sessione;
- avviare sessione;
- includere librerie condivise;
- impostare exception handler JSON.

### Configurazione

`backend/config/config.php` legge da variabili ambiente con fallback locali:

- `DB_HOST`;
- `DB_NAME`;
- `DB_USER`;
- `DB_PASSWORD`;
- `API_URL`;
- `APP_URL`;
- `ALLOWED_ORIGINS`.

### Librerie condivise

`backend/lib/Database.php`:

- singleton PDO;
- DSN `mysql:host=...;dbname=...;charset=utf8mb4`;
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`;
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`;
- `PDO::ATTR_EMULATE_PREPARES => false`.

`backend/lib/Response.php`:

- `json_response(array $payload, int $status = 200)`;
- `input_json()`.

`backend/lib/Auth.php`:

- `current_user()`;
- `require_user()`;
- `require_role(array $allowedRoles)`;
- `can_manage(array $user)`.

### Endpoint disponibili

#### POST /api/auth/login.php

Autentica utente.

Request:

```json
{
  "email": "admin@palestra.local",
  "password": "password"
}
```

Response 200:

```json
{
  "user": {
    "id": 1,
    "full_name": "Admin Palestra",
    "email": "admin@palestra.local",
    "role": "admin",
    "created_at": "2026-..."
  }
}
```

Errori:

- `422` se email o password sono vuote;
- `401` se credenziali non valide;
- `405` se metodo non consentito.

#### GET /api/auth/me.php

Restituisce utente corrente oppure `null`.

Response:

```json
{
  "user": {
    "id": 1,
    "full_name": "Admin Palestra",
    "email": "admin@palestra.local",
    "role": "admin",
    "created_at": "..."
  }
}
```

Oppure:

```json
{
  "user": null
}
```

#### POST /api/auth/logout.php

Chiude sessione.

Response:

```json
{
  "ok": true
}
```

#### GET /api/users/index.php

Richiede ruolo `admin` o `istruttore`.

Response:

```json
{
  "users": [
    {
      "id": 1,
      "full_name": "Admin Palestra",
      "email": "admin@palestra.local",
      "role": "admin",
      "created_at": "..."
    }
  ]
}
```

Errori:

- `401` non autenticato;
- `403` ruolo non autorizzato.

#### POST /api/users/index.php

Crea utente.

Richiede ruolo `admin` o `istruttore`.

Request:

```json
{
  "full_name": "Mario Rossi",
  "email": "mario@example.com",
  "password": "Password123!",
  "role": "atleta"
}
```

Response 201:

```json
{
  "id": 4
}
```

Validazioni:

- `full_name` obbligatorio;
- email valida con `FILTER_VALIDATE_EMAIL`;
- password almeno 8 caratteri;
- ruolo in `admin`, `istruttore`, `atleta`;
- solo admin puo creare altri admin.

Errori:

- `422` input non valido;
- `403` istruttore prova a creare admin;
- `409` email gia registrata.

#### GET /api/users/show.php?id=ID

Dettaglio utente.

Richiede ruolo `admin` o `istruttore`.

Response:

```json
{
  "user": {
    "id": 3,
    "full_name": "Atleta Demo",
    "email": "atleta@palestra.local",
    "role": "atleta",
    "created_at": "..."
  },
  "plans": [
    {
      "id": 1,
      "name": "Programma Base",
      "created_at": "..."
    }
  ]
}
```

Errori:

- `422` ID non valido;
- `404` utente non trovato;
- `401` non autenticato;
- `403` ruolo non autorizzato.

#### GET /api/workouts/index.php

Lista programmi.

Comportamento:

- admin/istruttore: tutti i programmi;
- atleta: solo programmi assegnati.

Response:

```json
{
  "plans": [
    {
      "id": 1,
      "name": "Programma Base",
      "assigned_user_id": 3,
      "assigned_user_name": "Atleta Demo",
      "created_at": "..."
    }
  ]
}
```

#### POST /api/workouts/index.php

Crea programma e genera automaticamente 7 day.

Richiede ruolo `admin` o `istruttore`.

Request:

```json
{
  "name": "Forza 4 settimane",
  "assigned_user_id": 3
}
```

Response 201:

```json
{
  "id": 1
}
```

Validazioni:

- `assigned_user_id` obbligatorio;
- utente assegnato deve esistere;
- utente assegnato deve avere ruolo `atleta`.

#### GET /api/workouts/show.php?id=ID

Dettaglio programma con day ed esercizi.

Accesso:

- admin/istruttore: qualsiasi programma;
- atleta: solo se assegnato al programma.

Response:

```json
{
  "plan": {
    "id": 1,
    "name": "Forza 4 settimane",
    "assigned_user_id": 3,
    "created_by": 1,
    "created_at": "...",
    "assigned_user_name": "Atleta Demo",
    "days": [
      {
        "id": 10,
        "day_number": 1,
        "title": "Day 1",
        "exercises": [
          {
            "id": 100,
            "workout_day_id": 10,
            "name": "Squat",
            "sets": "4",
            "reps": "8",
            "weight": "80 kg",
            "rest": "120s",
            "notes": "Tecnica controllata",
            "order_index": 1
          }
        ]
      }
    ]
  }
}
```

#### PUT /api/workouts/show.php?id=ID

Aggiorna programma completo.

Richiede ruolo `admin` o `istruttore`.

Request:

```json
{
  "id": 1,
  "name": "Forza 4 settimane",
  "assigned_user_id": 3,
  "days": [
    {
      "id": 10,
      "day_number": 1,
      "title": "Day 1 - Gambe",
      "exercises": [
        {
          "name": "Squat",
          "sets": "4",
          "reps": "8",
          "weight": "80 kg",
          "rest": "120s",
          "notes": "Tecnica controllata",
          "order_index": 1
        }
      ]
    }
  ]
}
```

Comportamento:

- aggiorna nome programma;
- aggiorna atleta assegnato;
- valida atleta assegnato;
- valida che i day inviati appartengano al programma;
- aggiorna titolo day;
- cancella e reinserisce gli esercizi dei day inviati.

Questa scelta semplifica l'MVP: il frontend invia lo stato completo del programma, il backend riscrive gli esercizi per ogni day.

Response:

```json
{
  "plan": {
    "id": 1,
    "name": "Forza 4 settimane",
    "days": []
  }
}
```

#### DELETE /api/workouts/show.php?id=ID

Elimina programma.

Richiede ruolo `admin` o `istruttore`.

Response:

```json
{
  "ok": true
}
```

Grazie ai vincoli `ON DELETE CASCADE`, eliminando un programma vengono eliminati day ed esercizi collegati.

### Gestione errori

Pattern errori JSON:

```json
{
  "error": "Messaggio errore"
}
```

Codici usati:

- `401`: autenticazione richiesta;
- `403`: permesso negato;
- `404`: risorsa non trovata;
- `405`: metodo non consentito;
- `409`: conflitto, ad esempio email duplicata;
- `422`: input non valido;
- `500`: errore interno del server.

## 6. Frontend

### Routing

Il routing e hash-based e implementato in `frontend/src/App.jsx`:

```js
function routeFromHash() {
  const hash = window.location.hash.replace(/^#/, '') || '/';
  const [path, query = ''] = hash.split('?');
  return { path, params: new URLSearchParams(query) };
}
```

Route:

- `#/`: dashboard;
- `#/workouts`: allenamenti;
- `#/users`: lista utenti, solo admin/istruttore;
- `#/user?id=ID`: dettaglio utente, solo admin/istruttore;
- `#/plan?id=ID`: editor o vista programma.

Hash routing riduce i problemi su hosting tradizionale. In aggiunta, la build include `.htaccess` per fallback React.

### Gestione stato

Stato principale:

- `user`: utente autenticato;
- `loading`: verifica sessione iniziale;
- `route`: route hash corrente.

Stato locale pagine:

- `Login`: email, password, errore;
- `MyWorkouts`: lista programmi, lista utenti, form nuovo programma;
- `UsersPage`: lista utenti, form creazione utente;
- `UserDetail`: utente e programmi assegnati;
- `PlanEditor`: programma completo e lista utenti.

Non sono usati Redux, Zustand o Context API. Lo stato e volutamente locale e semplice.

### API client

File:

```text
frontend/src/api.js
```

`request(path, options)`:

- costruisce URL con `VITE_API_BASE` o `/api`;
- invia `credentials: 'include'`;
- usa `Content-Type: application/json`;
- parse JSON response;
- lancia `Error` se `response.ok` e falso.

Metodi esportati:

- `me()`;
- `login(email, password)`;
- `logout()`;
- `users()`;
- `user(id)`;
- `createUser(payload)`;
- `plans()`;
- `createPlan(payload)`;
- `plan(id)`;
- `savePlan(id, payload)`;
- `deletePlan(id)`.

### Struttura componenti

Componenti principali in `frontend/src/App.jsx`:

- `App`;
- `Login`;
- `Shell`;
- `NavItem`;
- `Dashboard`;
- `MyWorkouts`;
- `UsersPage`;
- `UserDetail`;
- `PlanEditor`;
- `fieldLabel`.

### Layout responsive

CSS in `frontend/src/styles.css`.

Desktop:

- `.app-shell` con grid `260px 1fr`;
- `.sidebar` sticky full height;
- `.topbar` in alto;
- pagine con max width `1180px`;
- editor esercizi in grid multi-colonna.

Mobile sotto `900px`:

- app shell a colonna singola;
- sidebar fixed off-canvas;
- menu mobile visibile;
- overlay per chiusura menu;
- toolbar e form in colonna;
- editor esercizi in colonna singola.

### Pagine create

#### Login

Pagina full screen con immagine SVG di background locale, card login e brand mark.

#### Dashboard

Mostra card azione per allenamenti e, se permesso, utenti.

#### I miei allenamenti

Lista programmi. Admin/istruttore possono creare nuovo programma selezionando atleta.

#### Utenti

Lista utenti e form creazione utente. Visibile solo a admin/istruttore.

#### Dettaglio utente

Mostra dati utente e programmi assegnati.

#### Creazione/modifica programma

L'editor e usato dopo creazione programma o apertura programma esistente. Permette gestione Day 1-7 ed esercizi.

## 7. Docker

### Development: docker-compose.yml

Servizi:

- `mysql`;
- `backend`;
- `frontend`.

Porte pubbliche:

- `5173:5173` per Vite;
- `8000:8000` per PHP built-in server.

MySQL non espone porte host. Rimane accessibile nella rete Docker come:

```text
mysql:3306
```

### Servizio mysql

Image:

```text
mysql:8.4
```

Variabili:

- `MYSQL_ROOT_PASSWORD`;
- `MYSQL_DATABASE`;
- `MYSQL_USER`;
- `MYSQL_PASSWORD`.

Volumi:

- `mysql_data:/var/lib/mysql`;
- `./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro`;
- `./database/seed.sql:/docker-entrypoint-initdb.d/02-seed.sql:ro`.

Il seed viene importato solo quando il volume e vuoto. Per reimportare:

```bash
docker compose down -v
docker compose up --build
```

### Servizio backend development

Build:

```text
docker/php/Dockerfile
```

Base image:

```text
php:8.3-cli-alpine
```

Estensioni:

- `pdo`;
- `pdo_mysql`.

Command:

```bash
php -S 0.0.0.0:8000 -t /var/www/backend
```

Volume:

```text
./backend:/var/www/backend
```

### Servizio frontend development

Build:

```text
docker/frontend.Dockerfile
```

Base image:

```text
node:22-alpine
```

Command:

```bash
npm run dev -- --host 0.0.0.0
```

Volumi:

- `./frontend:/app`;
- `frontend_node_modules:/app/node_modules`.

Variabili:

- `VITE_API_BASE=/api`;
- `VITE_PROXY_TARGET=http://backend:8000`.

### Networking

Docker Compose crea una rete interna. I servizi comunicano via DNS di servizio:

- frontend -> backend: `http://backend:8000`;
- backend -> mysql: `mysql:3306`.

Dal browser:

- frontend: `http://localhost:5173`;
- API diretta: `http://localhost:8000/api/...`;
- API via proxy Vite: `http://localhost:5173/api/...`.

### Produzione locale: docker-compose.prod.yml

Servizi:

- `mysql`;
- `backend` PHP-FPM;
- `nginx`.

Porte pubbliche:

- `8080:80` per Nginx.

Build backend:

```text
docker/php-fpm/Dockerfile
```

Base image:

```text
php:8.3-fpm-alpine
```

Nginx:

- image `nginx:1.27-alpine`;
- monta `frontend/dist` in `/usr/share/nginx/html`;
- monta `backend` in `/var/www/backend`;
- usa `docker/nginx/default.conf`.

Uso:

```bash
docker compose run --rm frontend npm run build
docker compose -f docker-compose.prod.yml up --build
```

## 8. Deploy Aruba

### Build frontend

Nel frontend:

```bash
cd frontend
npm install
npm run build
```

La build genera:

```text
frontend/dist/
```

Contenuto atteso:

- `index.html`;
- `assets/`;
- `manifest.json`;
- `service-worker.js`;
- `icons/`;
- `images/`;
- `.htaccess`.

### Upload backend

Caricare nel web root Aruba:

```text
/api
/config
/lib
```

Origine:

- `backend/api/` -> `/api/`;
- `backend/config/` -> `/config/`;
- `backend/lib/` -> `/lib/`.

Caricare anche il contenuto di `frontend/dist/` nella root pubblica del sito.

Struttura consigliata:

```text
/index.html
/.htaccess
/assets/...
/manifest.json
/service-worker.js
/icons/icon.svg
/images/gym-bg.svg
/api/auth/login.php
/api/auth/me.php
/api/auth/logout.php
/api/users/index.php
/api/users/show.php
/api/workouts/index.php
/api/workouts/show.php
/config/config.php
/lib/Auth.php
/lib/Database.php
/lib/Response.php
```

### Configurazione MySQL Aruba

Nel pannello Aruba:

1. creare database MySQL;
2. importare `database/schema.sql`;
3. importare `database/seed.sql` solo se si vogliono utenti demo;
4. modificare `/config/config.php` con:
   - host database Aruba;
   - nome database;
   - utente;
   - password;
   - URL app;
   - allowed origins.

Esempio:

```php
return [
    'db_host' => 'host_mysql_aruba',
    'db_name' => 'nome_database',
    'db_user' => 'utente_database',
    'db_pass' => 'password_database',
    'api_url' => 'https://www.tuodominio.it/api',
    'app_url' => 'https://www.tuodominio.it',
    'allowed_origins' => ['https://www.tuodominio.it'],
];
```

### .htaccess React routing

File:

```text
frontend/public/.htaccess
```

Contenuto:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !^/api/
  RewriteRule . /index.html [L]
</IfModule>
```

Serve a evitare errori al refresh su route frontend. Anche se il routing attuale e hash-based, questo fallback resta utile se in futuro si passa a history routing o si caricano percorsi non statici.

### .htaccess backend

File:

```text
backend/.htaccess
```

Contiene:

- `Options -Indexes`;
- header sicurezza base se `mod_headers` e disponibile.

### Variabili ambiente

In Docker vengono usate da `.env`. Su Aruba, se le variabili ambiente non sono disponibili, configurare direttamente `config.php`.

Variabili supportate:

- `DB_HOST`;
- `DB_NAME`;
- `DB_USER`;
- `DB_PASSWORD`;
- `DB_ROOT_PASSWORD` solo Docker MySQL;
- `API_URL`;
- `APP_URL`;
- `ALLOWED_ORIGINS`;
- `VITE_API_BASE`;
- `VITE_PROXY_TARGET`.

Per build Aruba:

```text
VITE_API_BASE=/api
```

## 9. Testing

### Script smoke test

File:

```text
scripts/test-api.ps1
```

Default:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/test-api.ps1
```

Base URL default:

```text
http://localhost:8000/api
```

Test via Vite proxy:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/test-api.ps1 -BaseUrl http://localhost:5173/api
```

Lo script verifica:

- login admin;
- persistenza sessione admin;
- creazione utente atleta;
- lista utenti;
- creazione programma;
- caricamento programma;
- presenza di 7 day;
- salvataggio esercizio;
- login atleta;
- lista programmi atleta;
- blocco atleta su `/users` con `403`;
- eliminazione programma.

### Lint PHP

```bash
docker compose exec backend sh -c "find /var/www/backend -name '*.php' -print -exec php -l {} \;"
```

### Build frontend

```bash
docker compose run --rm frontend npm run build
```

## 10. Roadmap Futura

### Tracking progressi

Aggiungere tabelle per registrare performance per esercizio:

- carico usato;
- ripetizioni effettive;
- RPE;
- completato/non completato;
- data allenamento.

Possibili tabelle:

- `workout_sessions`;
- `exercise_logs`.

### Storico allenamenti

Consentire all'atleta di iniziare una sessione da un programma assegnato e salvare lo storico, separando programma previsto da allenamento eseguito.

### Timer recupero

Integrare un timer per il campo `rest`, con notifiche locali e stato persistente per sessione.

### Notifiche push

Estendere la PWA con Push API:

- reminder allenamento;
- notifiche nuovi programmi;
- promemoria recupero.

Richiede gestione subscription backend.

### Caricamento immagini esercizi

Aggiungere upload immagini o link media per esercizi:

- immagini dimostrative;
- video;
- note tecniche visuali.

Su Aruba tradizionale si puo usare filesystem locale con validazione MIME e dimensione.

### Schede PDF

Generare PDF programma allenamento:

- lato backend con libreria PHP compatibile;
- oppure lato frontend con stampa CSS.

### App mobile

Possibili strade:

- mantenere PWA installabile;
- creare wrapper Capacitor;
- in futuro app React Native che consuma le stesse API.

### Analytics

Dashboard per admin/istruttore:

- numero atleti attivi;
- programmi assegnati;
- completamento allenamenti;
- progressione carichi.

### Calendario allenamenti

Aggiungere calendario settimanale/mensile:

- assegnazione giorni;
- promemoria;
- storico completamenti.

## 11. MASTER PROMPT rigenerabile

Usa il seguente prompt in una nuova chat AI per rigenerare il progetto da zero.

```text
Sei un AI engineer senior. Crea da zero una web-app responsive/PWA per gestione palestra, pensata per hosting Linux Aruba tradizionale, senza Supabase, Firebase o servizi esterni obbligatori.

Stack obbligatorio:
- Frontend: React + Vite.
- Backend: PHP 8 senza framework, API REST JSON.
- Database: MySQL.
- Auth: sessioni PHP cookie-based, non JWT.
- Docker: Docker Compose per development e compose separato per produzione locale con Nginx.
- PWA: manifest, service worker base e icona app.

Obiettivo ambiente development:
- `docker compose up` deve avviare solo:
  - frontend React/Vite su `http://localhost:5173`;
  - backend PHP su `http://localhost:8000`;
  - MySQL interno raggiungibile dai container come `mysql:3306`.
- Non esporre MySQL sulla macchina host.
- Non usare Nginx in development.
- Il frontend deve proxyare `/api` verso `http://backend:8000`.

Obiettivo produzione locale:
- Crea `docker-compose.prod.yml`.
- Usa Nginx su `http://localhost:8080`.
- Nginx deve servire `frontend/dist`.
- Nginx deve inoltrare `/api/*.php` a PHP-FPM.
- Backend produzione locale con `php:8.3-fpm-alpine`.

Struttura progetto da creare:
.
|-- .env.example
|-- README.md
|-- docker-compose.yml
|-- docker-compose.prod.yml
|-- backend
|   |-- .htaccess
|   |-- api
|   |   |-- bootstrap.php
|   |   |-- auth
|   |   |   |-- login.php
|   |   |   |-- logout.php
|   |   |   `-- me.php
|   |   |-- users
|   |   |   |-- index.php
|   |   |   `-- show.php
|   |   `-- workouts
|   |       |-- index.php
|   |       `-- show.php
|   |-- config
|   |   |-- config.example.php
|   |   `-- config.php
|   |-- lib
|   |   |-- Auth.php
|   |   |-- Database.php
|   |   `-- Response.php
|   `-- tools
|       `-- hash-password.php
|-- database
|   |-- schema.sql
|   `-- seed.sql
|-- docker
|   |-- frontend.Dockerfile
|   |-- nginx
|   |   `-- default.conf
|   |-- php
|   |   `-- Dockerfile
|   `-- php-fpm
|       `-- Dockerfile
|-- frontend
|   |-- .env.example
|   |-- index.html
|   |-- package.json
|   |-- vite.config.js
|   |-- public
|   |   |-- .htaccess
|   |   |-- manifest.json
|   |   |-- service-worker.js
|   |   |-- icons
|   |   |   `-- icon.svg
|   |   `-- images
|   |       `-- gym-bg.svg
|   `-- src
|       |-- App.jsx
|       |-- api.js
|       |-- main.jsx
|       `-- styles.css
`-- scripts
    `-- test-api.ps1

Database MySQL:
Crea `database/schema.sql` con queste tabelle InnoDB utf8mb4:

1. users
- id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- full_name VARCHAR(120) NOT NULL
- email VARCHAR(180) NOT NULL UNIQUE
- password_hash VARCHAR(255) NOT NULL
- role ENUM('admin','istruttore','atleta') NOT NULL DEFAULT 'atleta'
- created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

2. workout_plans
- id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- name VARCHAR(160) NOT NULL
- assigned_user_id FK users(id) ON DELETE CASCADE
- created_by FK users(id) ON DELETE RESTRICT
- created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

3. workout_days
- id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- workout_plan_id FK workout_plans(id) ON DELETE CASCADE
- day_number TINYINT UNSIGNED NOT NULL
- title VARCHAR(120) NOT NULL
- UNIQUE(workout_plan_id, day_number)

4. exercises
- id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- workout_day_id FK workout_days(id) ON DELETE CASCADE
- name VARCHAR(160) NOT NULL
- sets VARCHAR(40)
- reps VARCHAR(40)
- weight VARCHAR(40)
- rest VARCHAR(40)
- notes TEXT
- order_index INT UNSIGNED DEFAULT 1
- INDEX(workout_day_id, order_index)

Crea `database/seed.sql` con tre utenti demo:
- Admin Palestra, admin@palestra.local, role admin
- Istruttore Demo, coach@palestra.local, role istruttore
- Atleta Demo, atleta@palestra.local, role atleta
Password demo per tutti: `password`, salvata con hash bcrypt compatibile con PHP `password_verify()`.

Backend PHP:
- Usa PDO MySQL con prepared statements e `ATTR_EMULATE_PREPARES=false`.
- Password: `password_hash()` e `password_verify()`.
- Sessioni PHP con cookie `HttpOnly`, `SameSite=Lax`, `secure` quando HTTPS.
- CORS configurabile con `ALLOWED_ORIGINS`, fallback ad `APP_URL`.
- Tutte le risposte API devono essere JSON.
- Gestisci errori con JSON `{ "error": "..." }`.
- Crea helper:
  - `json_response()`
  - `input_json()`
  - `Database::connection()`
  - `current_user()`
  - `require_user()`
  - `require_role()`
  - `can_manage()`

Endpoint:
1. `POST /api/auth/login.php`
- request `{ "email": "...", "password": "..." }`
- verifica credenziali
- `session_regenerate_id(true)`
- response `{ "user": {...} }`

2. `GET /api/auth/me.php`
- response `{ "user": user|null }`

3. `POST /api/auth/logout.php`
- distrugge sessione
- response `{ "ok": true }`

4. `GET /api/users/index.php`
- solo admin/istruttore
- lista utenti senza password_hash

5. `POST /api/users/index.php`
- solo admin/istruttore
- crea utente
- valida full_name, email, password min 8, role valido
- istruttore non puo creare admin
- email duplicata -> 409

6. `GET /api/users/show.php?id=ID`
- solo admin/istruttore
- dettaglio utente e programmi assegnati

7. `GET /api/workouts/index.php`
- admin/istruttore vedono tutti i programmi
- atleta vede solo i programmi con assigned_user_id uguale al proprio id

8. `POST /api/workouts/index.php`
- solo admin/istruttore
- crea programma
- assigned_user_id obbligatorio e deve essere atleta
- crea automaticamente 7 record workout_days con Day 1...Day 7

9. `GET /api/workouts/show.php?id=ID`
- admin/istruttore possono vedere tutto
- atleta solo se programma assegnato
- restituisce plan con days ed exercises

10. `PUT /api/workouts/show.php?id=ID`
- solo admin/istruttore
- aggiorna name, assigned_user_id, titoli day
- valida che day id appartengano al programma
- per ogni day inviato elimina e reinserisce exercises

11. `DELETE /api/workouts/show.php?id=ID`
- solo admin/istruttore
- elimina programma

Frontend React:
- Crea SPA in `frontend`.
- Usa `lucide-react` per icone.
- Usa routing hash-based, senza React Router.
- Route:
  - `#/` dashboard
  - `#/workouts` allenamenti
  - `#/users` utenti solo admin/istruttore
  - `#/user?id=ID` dettaglio utente solo admin/istruttore
  - `#/plan?id=ID` editor/vista programma
- `api.js` deve centralizzare fetch con `credentials: 'include'`.
- All'avvio chiama `/auth/me.php`.
- Se non autenticato mostra login.
- Se autenticato mostra shell con sidebar.
- Sidebar desktop sempre visibile; mobile off-canvas con overlay.
- Dashboard con azioni principali.
- Pagina utenti: lista e form creazione utente.
- Pagina dettaglio utente: dati utente e programmi assegnati.
- Pagina allenamenti: lista programmi; admin/istruttore possono creare programma selezionando atleta.
- Editor programma: Day 1-7, esercizi con nome, serie, ripetizioni, peso, recupero, note, ordine; admin/istruttore possono modificare; atleta sola lettura.
- CSS responsive custom, professionale e semplice.
- Background login con asset locale SVG, non immagine remota.

PWA:
- `manifest.json` con name AthleoDesk, short_name AthleoDesk, display standalone.
- `service-worker.js` con cache app shell e asset statici.
- Non cacheare richieste `/api/`.
- Registra service worker in `main.jsx`.
- Aggiungi icona SVG in `public/icons/icon.svg`.

Docker:
- `.env.example` con:
  - DB_HOST=mysql
  - DB_NAME=gym_app
  - DB_USER=gym_user
  - DB_PASSWORD=gym_password
  - DB_ROOT_PASSWORD=root_password
  - API_URL=http://localhost:8000/api
  - APP_URL=http://localhost:5173
  - ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000
  - VITE_API_BASE=/api
  - VITE_PROXY_TARGET=http://backend:8000
- `docker-compose.yml` development:
  - mysql con volume `mysql_data` e import schema/seed
  - backend PHP CLI su porta 8000
  - frontend Vite su porta 5173
  - MySQL non deve esporre porte host
- `docker-compose.prod.yml`:
  - mysql
  - backend PHP-FPM
  - nginx su porta 8080
- `docker/nginx/default.conf`:
  - root `/usr/share/nginx/html`
  - fallback SPA a `/index.html`
  - inoltro `/api/*.php` a backend:9000

Testing:
- Crea `scripts/test-api.ps1`.
- Deve testare:
  - login admin
  - sessione admin
  - creazione utente
  - lista utenti
  - creazione programma
  - presenza 7 day
  - aggiornamento esercizio
  - login atleta
  - atleta bloccato su `/users` con 403
  - eliminazione programma
- Default BaseUrl `http://localhost:8000/api`.
- Deve accettare parametro `-BaseUrl` per testare anche `http://localhost:5173/api`.

README:
- Documenta utenti demo.
- Documenta `docker compose up`.
- Specifica che in development espone solo 5173 e 8000.
- Documenta reset database con `docker compose down -v`.
- Documenta lint PHP.
- Documenta smoke test.
- Documenta build produzione frontend.
- Documenta produzione locale con `docker-compose.prod.yml`.
- Documenta deploy Aruba Linux:
  - import schema SQL;
  - config `backend/config/config.php`;
  - build frontend;
  - upload `frontend/dist` nella root;
  - upload `backend/api` come `/api`;
  - upload `backend/config` come `/config`;
  - upload `backend/lib` come `/lib`;
  - `.htaccess` per fallback React.

Esegui realmente:
- `docker compose up --build -d --remove-orphans`
- lint PHP
- smoke test diretto su `http://localhost:8000/api`
- smoke test via Vite proxy su `http://localhost:5173/api`
- build frontend produzione

Correggi eventuali errori trovati e consegna un riepilogo tecnico finale.
```
