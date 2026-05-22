# Palestra Fitness 360 API

Base URL development: `http://localhost:8000/api`.

Tutte le risposte sono JSON. Le richieste autenticate usano sessione PHP cookie-based con `credentials: include`.

## CSRF

Le richieste `POST`, `PUT` e `DELETE`, esclusi gli endpoint pubblici di autenticazione (`login`, `register`, `verify-email`, `request-password-reset`, `reset-password`), richiedono header:

```http
X-CSRF-Token: <csrf_token>
```

Il token viene restituito da:

- `GET /auth/me.php`
- `POST /auth/login.php`

## Auth

- `POST /auth/login.php`
  - body: `{ "email": "...", "password": "..." }`
  - response: `{ "user": {...}, "csrf_token": "..." }`
  - blocca account `pending`, `disabled` o email non verificata.

- `POST /auth/register.php`
  - pubblico, rate limited
  - body: `{ "first_name": "...", "last_name": "...", "email": "...", "password": "...", "password_confirm": "...", "accepted_terms": true, "website": "" }`
  - `website` e un honeypot anti-spam e deve restare vuoto.
  - crea sempre un utente `atleta` con `status=pending` e invia email di verifica.
  - response: `{ "ok": true, "message": "..." }`

- `GET|POST /auth/verify-email.php`
  - pubblico
  - query/body: `token`
  - token salvato solo come SHA-256 nel database, valido 24 ore e monouso.
  - response: `{ "ok": true, "message": "Email verificata..." }`

- `POST /auth/request-password-reset.php`
  - pubblico, rate limited
  - body: `{ "email": "..." }`
  - response sempre generica: `{ "ok": true, "message": "Se l email esiste, riceverai un link" }`
  - non rivela se l'email e presente nel database.

- `POST /auth/reset-password.php`
  - pubblico
  - body: `{ "token": "...", "password": "...", "password_confirm": "..." }`
  - token valido 60 minuti e monouso; dopo il reset invalida gli altri token attivi dello stesso utente.

- `GET /auth/me.php`
  - response: `{ "user": user|null, "csrf_token": "..." }`

- `POST /auth/logout.php`
  - richiede CSRF
  - response: `{ "ok": true }`

## Users

- `GET /users/index.php`
  - ruoli: `admin`, `istruttore`
  - campi principali: `id`, `full_name`, `email`, `role`, `status`, `email_verified_at`, `created_at`
  - response: `{ "users": [...] }`

- `POST /users/index.php`
  - ruoli: `admin`, `istruttore`
  - richiede CSRF
  - istruttore non puo creare admin
  - body: `{ "full_name": "...", "email": "...", "password": "...", "role": "atleta" }`

- `GET /users/show.php?id=ID`
  - ruoli: `admin`, `istruttore`
  - response: `{ "user": {...}, "plans": [...], "sessions": [...] }`

- `PUT /users/show.php?id=ID`
  - ruoli: `admin`, `istruttore`
  - richiede CSRF
  - istruttore non puo modificare admin
  - puo aggiornare `status` tra `pending`, `active`, `disabled`
  - admin puo gestire tutti tranne disabilitare se stesso; istruttore puo gestire solo atleti.

- `DELETE /users/show.php?id=ID`
  - solo `admin`
  - richiede CSRF

## Workouts

- `GET /workouts/index.php`
  - admin/istruttore: tutti
  - atleta: solo assegnati

- `POST /workouts/index.php`
  - ruoli: `admin`, `istruttore`
  - richiede CSRF
  - crea automaticamente Day 1-7

- `GET /workouts/show.php?id=ID`
  - include days ed exercises

- `PUT /workouts/show.php?id=ID`
  - ruoli: `admin`, `istruttore`
  - richiede CSRF

- `DELETE /workouts/show.php?id=ID`
  - ruoli: `admin`, `istruttore`
  - richiede CSRF

## Exercise Library

- `GET /exercises/index.php?q=&muscle_group=`
  - autenticato
  - filtri: `petto`, `schiena`, `gambe`, `spalle`, `braccia`, `addome`, `cardio`

## Sessions

- `GET /sessions/index.php`
  - atleta: proprio storico
  - admin/istruttore: `?user_id=ID`

- `POST /sessions/index.php`
  - richiede CSRF
  - body: `{ "workout_plan_id": 1 }`

## Dashboard

- `GET /dashboard/stats.php`
  - ritorna statistiche differenziate per ruolo.

## Errori

Formato:

```json
{ "error": "Messaggio" }
```

Codici usati: `401`, `403`, `404`, `405`, `409`, `419`, `422`, `429`, `500`.
