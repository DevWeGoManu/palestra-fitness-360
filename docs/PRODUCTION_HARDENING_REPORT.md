# Production Hardening Report

Data: 2026-05-14

## Obiettivo

Stabilizzare Palestra Fitness 360 PWA per un uso reale su hosting Linux Aruba, senza aggiungere feature grandi o dipendenze esterne.

## Problemi trovati e corretti

- Mancava protezione CSRF sulle richieste mutanti: aggiunto token sessione e header `X-CSRF-Token`.
- Rate limiting login solo session-based: sostituito con rate limit file-based per IP/email.
- Mancavano timeout sessione e rigenerazione periodica: aggiunti TTL e `session_regenerate_id`.
- Header sicurezza incompleti: aggiunti `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP base.
- Logging non strutturato: aggiunto `log_event()` JSONL con fallback a `error_log()`.
- Backup MySQL Docker falliva sui tablespace senza privilegi: aggiunto `--no-tablespaces`.
- Bundle frontend monolitico: introdotto lazy loading pagine e code splitting.
- Timer toast globale su `window`: sostituito con `useRef` e cleanup.
- Service worker cacheava potenzialmente metodi non GET: limitato a GET e mai `/api/`.

## Hardening implementato

- CSRF protection per `POST`, `PUT`, `DELETE`.
- Login escluso da CSRF ma protetto da rate limit.
- API rate limit per richieste mutanti.
- Session timeout configurabile con `SESSION_TTL`.
- Audit log per login, logout, CRUD utenti, CRUD programmi, completamenti.
- Placeholder validazione MIME futura.
- CSP base su API, Nginx e `.htaccess` Aruba.
- Cache headers e gzip per produzione.

## Performance

Build finale:

- main JS: circa 204 KB non compresso, 64.8 KB gzip.
- CSS: circa 7.6 KB non compresso, 2.4 KB gzip.
- pagine lazy separate in chunk dedicati.

## Test eseguiti

- `docker compose up -d --build --remove-orphans`
- PHP lint completo.
- Build frontend produzione.
- Smoke test API diretto su `8000`.
- Smoke test API via proxy Vite su `5173`.
- Test CSRF e secure headers.
- Test refresh/hash route base.
- Test manifest e service worker raggiungibili.
- Backup database Docker.
- Validazione compose produzione.
- Stress test login con rate limit attivato.

## Limiti test

Non sono disponibili nel container strumenti browser real-device per Android Chrome o Safari iPhone. La verifica mobile e stata fatta a livello CSS/build/statico; prima del go-live va eseguita manualmente su dispositivi reali.

## Rischi residui

- Rate limit file-based adatto a hosting singolo, non a cluster multi-server.
- Log file su Aruba dipende dai permessi cartella.
- Nessun sistema automatico di rotazione log.
- Nessuna gestione reset password self-service.
- Nessun test end-to-end browser con Playwright/Cypress.

## Consigli produzione

- Cambiare subito le password demo.
- Usare solo HTTPS.
- Impostare `allowed_origins` al dominio reale.
- Eseguire backup prima di ogni deploy.
- Verificare stampa PDF su Chrome e Safari.
- Abilitare log PHP Aruba.
- Pianificare rotazione periodica di `storage/logs/app.log`.

## Roadmap v2

- Test E2E con Playwright.
- Rotazione log e pannello audit admin.
- Reset password sicuro via email.
- Ruoli/permessi piu granulari.
- Versionamento programmi.
- Storico carichi per esercizio.
- Export PDF server-side se Aruba supporta una libreria compatibile.
