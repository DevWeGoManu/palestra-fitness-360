# Staging Deploy Aruba

Guida per il primo deploy reale di AthleoDesk PWA in ambiente staging su Aruba Linux.

## Scelta ambiente staging

Opzione consigliata:

- sottodominio: `https://staging.tuodominio.it`

Alternativa:

- sottocartella: `https://www.tuodominio.it/staging`

Il sottodominio e preferibile per cookie, PWA, service worker e CORS, perche replica meglio la produzione finale.

## Prerequisiti Aruba

- Hosting Linux con PHP 8.x.
- MySQL attivo.
- HTTPS attivo sul dominio/sottodominio.
- `mod_rewrite` disponibile per fallback SPA.
- `mod_headers` consigliato per security/cache headers.
- `mod_deflate` opzionale per gzip.

## Build frontend staging

Nel progetto locale:

```bash
cd frontend
npm install
npm run build
```

La build viene generata in:

```text
frontend/dist/
```

## Configurazione database Aruba

1. Crea un database MySQL dedicato allo staging.
2. Importa:
   - `database/schema.sql`
   - opzionale `database/seed.sql`, solo se vuoi utenti demo temporanei
3. Per database esistenti applica anche:
   - `database/migrations_002_mvp.sql`

## Configurazione backend

Modifica il file `backend/config/config.php` prima dell'upload, oppure caricalo e modificalo sul server:

```php
<?php
return [
    'db_host' => 'HOST_MYSQL_ARUBA',
    'db_name' => 'NOME_DATABASE_STAGING',
    'db_user' => 'UTENTE_DATABASE',
    'db_pass' => 'PASSWORD_DATABASE',
    'api_url' => 'https://staging.tuodominio.it/api',
    'app_url' => 'https://staging.tuodominio.it',
    'allowed_origins' => ['https://staging.tuodominio.it'],
    'session_ttl' => 3600,
];
```

Per sottocartella:

```php
'api_url' => 'https://www.tuodominio.it/staging/api',
'app_url' => 'https://www.tuodominio.it/staging',
'allowed_origins' => ['https://www.tuodominio.it'],
```

Nota: con sottocartella, verifica attentamente service worker e percorsi asset. Il sottodominio resta la scelta piu pulita.

## File da caricare

### Root staging

Carica tutto il contenuto di:

```text
frontend/dist/
```

nella document root del sottodominio staging.

### API backend

Carica:

```text
backend/api/     -> /api/
backend/config/  -> /config/
backend/lib/     -> /lib/
```

### Cartella log

Se possibile crea:

```text
/storage/logs/
```

e proteggila da accesso pubblico. Se non e scrivibile, il backend usera `error_log()` del server.

## Permessi consigliati

- File: `0644`
- Cartelle: `0755`
- `config.php`: leggibile da PHP, non modificabile pubblicamente
- `storage/logs`: scrivibile dal processo PHP solo se vuoi logging su file

## .htaccess

La build include `.htaccess` copiato da `frontend/public/.htaccess`.

Verifica che sia presente nella root staging. Gestisce:

- fallback SPA;
- security headers;
- cache asset statici;
- gzip se disponibile.

## Verifiche dopo upload

1. Apri `https://staging.tuodominio.it`.
2. Verifica che HTTPS sia valido.
3. Apri DevTools e controlla assenza errori console.
4. Login admin.
5. Controlla cookie sessione:
   - `HttpOnly`
   - `SameSite=Lax`
   - `Secure` su HTTPS
6. Verifica `GET /api/auth/me.php`.
7. Crea un utente atleta test.
8. Crea un programma test.
9. Completa un allenamento.
10. Verifica storico.
11. Prova stampa/PDF scheda.
12. Installa PWA.
13. Riapri PWA installata e verifica login/sessione.

## Verifica PWA

Controllare:

- `manifest.json` risponde 200;
- `service-worker.js` risponde 200;
- icona `/icons/icon.svg` risponde 200;
- install prompt disponibile su Chrome Android;
- su iPhone Safari usare "Aggiungi alla schermata Home".

## Problemi comuni

- API 500: verificare `config.php`, credenziali MySQL e log Aruba.
- CORS: `allowed_origins` non corrisponde al dominio staging.
- Login non persiste: dominio diverso tra app e API, HTTPS non valido o cookie bloccato.
- Refresh pagina fallisce: `.htaccess` assente o `mod_rewrite` non attivo.
- PWA non installabile: HTTPS mancante, manifest non raggiungibile o service worker non registrato.
