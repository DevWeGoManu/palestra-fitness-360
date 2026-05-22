# Release Notes v1.0.0

## Funzionalita incluse

- PWA responsive per gestione palestra.
- Login email/password con sessioni PHP.
- Ruoli `admin`, `istruttore`, `atleta`.
- Dashboard differenziate per atleta e staff.
- CRUD utenti completo con permessi.
- Programmi allenamento Day 1-7.
- Editor esercizi con drag & drop, duplicazione esercizi/day e libreria predefinita.
- Assegnazione programmi ad atleta.
- Completamento allenamento e storico.
- Export scheda tramite stampa/PDF browser.
- Docker development e produzione locale.
- Deploy compatibile Aruba Linux.
- CSRF protection, rate limiting, secure headers, logging base.
- Backup/restore database Docker.

## Limiti noti

- Nessun reset password self-service.
- Nessuna rotazione automatica dei log.
- Nessun test E2E browser automatizzato.
- PDF generato tramite stampa browser, non PDF server-side.
- Rate limit file-based adatto a hosting singolo.
- Validazione mobile reale da completare su dispositivi fisici.

## Test eseguiti

- Build frontend produzione.
- PHP lint completo.
- Smoke test API diretto.
- Smoke test API via proxy Vite.
- Test CSRF e secure headers.
- Stress test login.
- Backup database Docker.
- Verifica manifest/service worker raggiungibili.
- Verifica compose produzione.

## Rischi residui

- Differenze configurazione Aruba rispetto a Docker locale.
- Permessi scrittura log non garantiti.
- Service worker da validare su sottocartella se non si usa sottodominio.
- Compatibilita Safari iPhone da verificare manualmente.

## Passaggi post-deploy

1. Cambiare o creare admin reale.
2. Rimuovere/disabilitare utenti demo.
3. Verificare HTTPS.
4. Verificare cookie/sessioni.
5. Eseguire checklist mobile.
6. Creare backup database staging.
7. Annotare configurazione Aruba usata.
