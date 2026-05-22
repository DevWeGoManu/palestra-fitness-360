# Backup e Recovery

## Backup Docker locale

```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup-db.ps1
```

Il backup viene creato in `backups/`.

## Restore Docker locale

```powershell
powershell -ExecutionPolicy Bypass -File scripts/restore-db.ps1 -File backups/gym_app_YYYY-MM-DD_HH-mm-ss.sql
```

## Export Aruba

Usare il pannello MySQL Aruba o phpMyAdmin:

1. selezionare database;
2. export SQL completo;
3. conservare il file fuori dallo spazio web pubblico.

## Strategia backup consigliata

- Giornaliero: database.
- Settimanale: intero spazio web.
- Prima di ogni deploy: database + cartella `/config`.
- Conservazione minima: 14 giorni.

## Recovery checklist

1. Mettere sito in manutenzione.
2. Salvare backup corrente prima del restore.
3. Ripristinare SQL.
4. Verificare login admin.
5. Verificare lista utenti/programmi.
6. Verificare completamento allenamento.
7. Riaprire sito.
