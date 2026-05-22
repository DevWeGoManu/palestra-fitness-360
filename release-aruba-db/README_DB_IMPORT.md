# Import Database Aruba

## Nuovo database

Importa:

1. `schema.sql`

`schema.sql` contiene gia la struttura aggiornata del progetto.

## Database esistente

Importa solo le migration non ancora applicate, in ordine:

1. `migrations_002_mvp.sql`
2. `migrations_003_auth_flows.sql`
3. `migrations_004_performance_indexes.sql`
4. `migrations_005_exercise_library_expansion.sql`
5. `migrations_006_day_sessions.sql`
6. `migrations_007_exercise_notes.sql`
7. `migrations_008_remove_exercise_library.sql`
8. `migrations_009_autonomous_role.sql`

Nota: la libreria esercizi non e piu usata dall'app. Nei nuovi database non viene creata; nei database esistenti la migration 008 rimuove la tabella `exercise_library`.

Nota: la migration 009 aggiunge il ruolo `autonomo`, pensato per utenti che possono creare/modificare solo le proprie schede senza vedere altri utenti.

Prima di importare su Aruba fai sempre un backup del database.
