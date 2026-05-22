# Workout Parser - Mini Demo Interna

## 1. Descrizione breve

Il Workout Parser permette a coach e admin di incollare una scheda scritta in testo libero e convertirla in una preview strutturata prima del salvataggio.

La feature è rule-based, quindi non usa AI/OpenAI. Il parser riconosce pattern comuni usati dai coach, specialmente testi copiati da WhatsApp o note rapide, e produce dati compatibili con il modello esistente di programma, day ed esercizi.

Il salvataggio non è automatico: dopo la preview bisogna applicare le modifiche alla scheda e poi premere `Salva`.

## 2. Esempi realistici input coach

### Esempio 1

```text
Day1: Stacco 3x5 con 100kg, Curl martello 2x12 con 12kg
```

### Esempio 2

```text
Giorno 1: front lever 3s per 3 serie, plank 30s per 3 serie
```

### Esempio 3

```text
Day2:
✅ Pull up
3x5 con 25kg
- Dip 3 x 12 @15kg
• Squat HB 1 con 120kg 2x6 con 90kg
```

### Esempio 4

```text
giorno 1: Anelli front lever in full 3x6”
```

### Esempio 5

```text
Day2: Potenziamento croce alla lat machine 15 con 5kg 5 con 8kg Max con 12kg Max con 8kg
```

## 3. Output atteso sintetico

### Esempio 1

- Day 1
- `Stacco`: serie `3`, reps `5`, peso `100kg`
- `Curl martello`: serie `2`, reps `12`, peso `12kg`

### Esempio 2

- Day 1
- `front lever`: serie `3`, durata `3s`
- `plank`: serie `3`, durata `30s`

### Esempio 3

- Day 2
- `Pull up`: serie `3`, reps `5`, peso `25kg`
- `Dip`: serie `3`, reps `12`, peso `15kg`
- `Squat HB`: serie `2`, reps `6`, peso `90kg`
- Note strutturate per Squat HB:
  - Serie `1`, rep `1`, peso `120kg`
  - Serie `2`, rep `6`, peso `90kg`

### Esempio 4

- Day 1
- `Anelli front lever in full`: serie `3`, durata `6s`

### Esempio 5

- Day 2
- `Potenziamento croce alla lat machine`
- Campi principali lasciati vuoti
- Note strutturate:
  - Serie `15`, rep `15`, peso `5kg`
  - Serie `5`, rep `5`, peso `8kg`
  - Serie `Max`, rep `Max`, peso `12kg`
  - Serie `Max`, rep `Max`, peso `8kg`

## 4. Limiti noti

- Il parser non capisce il significato semantico della scheda: riconosce pattern testuali.
- Frasi senza serie, reps, peso, recupero o note strutturate vengono ignorate.
- Formati molto ambigui possono finire nel campo `notes` oppure non essere rilevati.
- Non esegue correzioni automatiche sul nome esercizio.
- Non inventa valori mancanti.
- Il limite massimo input è `6000` caratteri.
- La feature non sostituisce la revisione del coach: la preview va sempre controllata prima di applicarla.

## 5. Come testarla localmente

Avvia i container:

```bash
docker compose up
```

Esegui la suite parser:

```bash
docker compose run --rm backend php /var/www/backend/tests/WorkoutParserTest.php
```

Esegui il lint PHP:

```bash
docker compose run --rm backend sh -c "find /var/www/backend -name '*.php' -print -exec php -l {} \;"
```

Esegui la build frontend:

```bash
docker compose run --rm -e VITE_BASE_PATH=/Crm/ -e VITE_API_BASE=/Crm/api frontend npm run build
```

Test manuale:

1. Accedi come coach/admin.
2. Apri una scheda atleta.
3. Incolla uno degli esempi nel Workout Parser.
4. Premi `Genera preview`.
5. Controlla la preview.
6. Premi `Aggiungi alla scheda` o `Sostituisci day trovati`.
7. Premi `Salva` solo dopo verifica.

## 6. Checklist prima del deploy Aruba

- [ ] `WorkoutParserTest OK`.
- [ ] PHP lint completo senza errori.
- [ ] Build frontend completata.
- [ ] `release-aruba/` rigenerata dopo la build.
- [ ] `release-aruba/api/workouts/parse.php` presente.
- [ ] `release-aruba/lib/WorkoutParser.php` presente.
- [ ] `release-aruba/config/config.php` configurato con dati Aruba reali.
- [ ] `VITE_API_BASE=/Crm/api` usato per la build produzione.
- [ ] Upload completo dentro `/Crm`.
- [ ] Test login coach/admin su Aruba.
- [ ] Test parser su Aruba con input breve.
- [ ] Test errore parser con testo non valido.
- [ ] Test salvataggio scheda dopo applicazione preview.

## 7. Note sicurezza/permessi

- Endpoint parser: `POST /api/workouts/parse.php`.
- Accesso consentito solo a `admin` e `istruttore`.
- Gli atleti non possono usare il parser.
- Le richieste `POST` passano dalla protezione CSRF già presente nel backend.
- Input massimo: `6000` caratteri.
- Il parser non salva direttamente sul database.
- Il salvataggio resta gestito da `PUT /api/workouts/show.php?id=...`.
- Gli errori API sono restituiti in JSON con campo `error`.
