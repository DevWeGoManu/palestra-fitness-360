import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, FileText, Wand2 } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { canEditWorkouts, canManage, go } from '../utils/router.js';

const emptyExercise = { name: '', sets: '', reps: '', weight: '', rest: '', notes: '', order_index: 1 };

export function PlanEditor({ id, user, notify, editMode = false }) {
  const [plan, setPlan] = useState(null);
  const [savedSnapshot, setSavedSnapshot] = useState('');
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [completedDayId, setCompletedDayId] = useState(null);
  const [completingDayId, setCompletingDayId] = useState(null);
  const [activeDayId, setActiveDayId] = useState(null);
  const [visibleDayIds, setVisibleDayIds] = useState([]);
  const [parserText, setParserText] = useState('');
  const [parserPreview, setParserPreview] = useState(null);
  const [parserLoading, setParserLoading] = useState(false);
  const [parserTargetDayNumber, setParserTargetDayNumber] = useState(1);
  const [manualEditing, setManualEditing] = useState(false);
  const [editSnapshot, setEditSnapshot] = useState('');
  const [confirmDialog, setConfirmDialog] = useState(null);
  const editable = canManage(user) || (editMode && canEditWorkouts(user));
  const canAssignAthletes = canManage(user);
  const showManualEditor = editable && manualEditing;
  const dirty = editable && plan && savedSnapshot && JSON.stringify(plan) !== savedSnapshot;

  async function load() {
    setLoading(true);
    try {
      const data = await api.plan(id);
      setPlan(data.plan);
      setSavedSnapshot(JSON.stringify(data.plan));
      setManualEditing(false);
      const daysWithExercises = data.plan.days.filter((day) => day.exercises.length > 0);
      const firstActiveDay = daysWithExercises[0] || data.plan.days[0];
      setActiveDayId(firstActiveDay?.id || null);
      setParserTargetDayNumber(Number(firstActiveDay?.day_number || 1));
      setVisibleDayIds((daysWithExercises.length ? daysWithExercises : [data.plan.days[0]]).filter(Boolean).map((day) => Number(day.id)));
      if (canAssignAthletes) {
        const userData = await api.users();
        setUsers(userData.users);
      } else if (!editable) {
        const sessionData = await api.sessions();
        const todaySession = (sessionData.sessions || []).find((session) => {
          return Number(session.workout_plan_id) === Number(id) && isToday(session.completed_at);
        });
        setCompletedDayId(todaySession?.workout_day_id ? Number(todaySession.workout_day_id) : null);
      }
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, [id]);
  useEffect(() => {
    const beforeUnload = (event) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);
    return () => window.removeEventListener('beforeunload', beforeUnload);
  }, [dirty]);

  const athletes = useMemo(() => users.filter((item) => ['atleta', 'autonomo'].includes(item.role)), [users]);
  const visibleDays = editable ? (plan?.days || []).filter((day) => visibleDayIds.includes(Number(day.id))) : (plan?.days || []).filter((day) => day.exercises.length > 0);
  const daysToRender = visibleDays.filter((day) => Number(day.id) === Number(activeDayId || visibleDays[0]?.id));
  const activeDay = daysToRender[0];
  const activeDayPosition = Math.max(0, visibleDays.findIndex((day) => Number(day.id) === Number(activeDay?.id)));
  const parserHasExplicitDays = /\b(?:day|giorno)\s*[1-7]\b/iu.test(parserText);

  if (loading || !plan) return <section className="page"><Loading /></section>;

  function getEffectiveParserDays() {
    const parsedDays = parserPreview?.days || [];
    if (parserHasExplicitDays) return parsedDays;

    return parsedDays.map((day) => ({
      ...day,
      day_number: Number(parserTargetDayNumber),
      name: dayLabel(parserTargetDayNumber),
      title: dayLabel(parserTargetDayNumber)
    }));
  }

  function updateDay(dayIndex, patch) {
    setPlan({ ...plan, days: plan.days.map((day, index) => index === dayIndex ? { ...day, ...patch } : day) });
  }

  function updateExercise(dayIndex, exerciseIndex, patch) {
    const days = plan.days.map((day, index) => {
      if (index !== dayIndex) return day;
      const exercises = day.exercises.map((exercise, itemIndex) => itemIndex === exerciseIndex ? { ...exercise, ...patch } : exercise);
      return { ...day, exercises };
    });
    setPlan({ ...plan, days });
  }

  function duplicateExercise(dayIndex, exerciseIndex) {
    const day = plan.days[dayIndex];
    const copy = { ...day.exercises[exerciseIndex], id: undefined, order_index: day.exercises.length + 1 };
    updateDay(dayIndex, { exercises: [...day.exercises, copy] });
  }

  function removeExercise(dayIndex, exerciseIndex) {
    const day = plan.days[dayIndex];
    updateDay(dayIndex, { exercises: day.exercises.filter((_, index) => index !== exerciseIndex).map((exercise, index) => ({ ...exercise, order_index: index + 1 })) });
  }

  function removeDay(dayIndex) {
    const day = plan.days[dayIndex];
    if (!day) return;

    requestConfirmation({
      title: `Eliminare ${day.title}?`,
      message: 'Il day verrà rimosso dalla scheda corrente. Dovrai comunque premere Salva per rendere definitiva la modifica.',
      confirmLabel: 'Elimina day',
      tone: 'danger',
      onConfirm: () => {
        updateDay(dayIndex, { exercises: [] });
        const remainingIds = visibleDayIds.filter((dayId) => Number(dayId) !== Number(day.id));
        const fallbackDay = plan.days.find((item) => remainingIds.includes(Number(item.id))) || day;

        setVisibleDayIds(remainingIds.length ? remainingIds : [Number(fallbackDay.id)]);
        setActiveDayId(Number(fallbackDay.id));
      }
    });
  }

  function startManualEditing() {
    const normalizedPlan = normalizePlanForManualEditing(plan);
    setPlan(normalizedPlan);
    setEditSnapshot(JSON.stringify(normalizedPlan));
    setManualEditing(true);
  }

  function cancelManualEditing() {
    if (editSnapshot && JSON.stringify(plan) !== editSnapshot) {
      requestConfirmation({
        title: 'Annullare le modifiche?',
        message: 'Le modifiche manuali fatte in questa sessione verranno scartate.',
        confirmLabel: 'Annulla modifiche',
        onConfirm: restoreEditSnapshot
      });
      return;
    }

    restoreEditSnapshot();
  }

  function restoreEditSnapshot() {
    if (editSnapshot) {
      setPlan(JSON.parse(editSnapshot));
    }
    setManualEditing(false);
    setEditSnapshot('');
  }

  async function save() {
    try {
      const data = await api.savePlan(plan.id, plan);
      setPlan(data.plan);
      setSavedSnapshot(JSON.stringify(data.plan));
      notify('Programma salvato');
      go(canManage(user) ? '/workouts' : `/plan?id=${data.plan.id}`);
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  async function deletePlan() {
    requestConfirmation({
      title: 'Eliminare il programma?',
      message: `"${plan.name}" verrà eliminato definitivamente. Questa azione non richiede il pulsante Salva.`,
      confirmLabel: 'Elimina programma',
      tone: 'danger',
      onConfirm: async () => {
        try {
          await api.deletePlan(plan.id);
          notify('Programma eliminato');
          go('/workouts');
        } catch (err) {
          notify(err.message, 'error');
        }
      }
    });
  }

  async function completeWorkout(day) {
    if (completingDayId || Number(completedDayId) === Number(day.id)) return;
    setCompletingDayId(day.id);
    try {
      const result = await api.completeWorkout(plan.id, day.id);
      setCompletedDayId(Number(result.workout_day_id || day.id));
      notify(`${day.title} completato`);
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setCompletingDayId(null);
    }
  }

  async function saveAthleteNote(exercise) {
    try {
      await api.saveExerciseNote(exercise.id, exercise.athlete_note || '');
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  async function parseWorkoutText() {
    setParserLoading(true);
    try {
      const data = await api.parseWorkoutText(parserText);
      setParserPreview(data);
      notify('Preview generata');
    } catch (err) {
      setParserPreview(null);
      notify(err.message, 'error');
    } finally {
      setParserLoading(false);
    }
  }

  function applyParserPreview(mode) {
    const parsedDays = getEffectiveParserDays();
    if (!parsedDays.length) return;

    const action = mode === 'replace' ? 'sostituire' : 'aggiungere a';
    const dayNames = parsedDays.map((day) => formatDayTitle(day)).join(', ');

    requestConfirmation({
      title: mode === 'replace' ? 'Sostituire i day trovati?' : 'Aggiungere alla scheda?',
      message: `Vuoi ${action} ${dayNames} nella scheda corrente? Dovrai comunque premere Salva per rendere definitive le modifiche.`,
      confirmLabel: mode === 'replace' ? 'Sostituisci' : 'Aggiungi',
      tone: mode === 'replace' ? 'danger' : 'default',
      onConfirm: () => applyParserDays(parsedDays, mode)
    });
  }

  function applyParserDays(parsedDays, mode) {
    const parsedByNumber = new Map(parsedDays.map((day) => [Number(day.day_number), day]));
    const nextVisibleIds = new Set(visibleDayIds.map(Number));
    const days = plan.days.map((day) => {
      const parsed = parsedByNumber.get(Number(day.day_number));
      if (!parsed) return day;

      nextVisibleIds.add(Number(day.id));
      const parsedExercises = parsed.exercises.flatMap((exercise) => expandParsedExercise(exercise))
        .map((exercise, index) => ({
          ...emptyExercise,
          ...exercise,
          id: undefined,
          workout_day_id: day.id,
          order_index: index + 1
        }));

      return {
        ...day,
        title: parsed.title || day.title,
        exercises: mode === 'replace'
          ? parsedExercises
          : [...day.exercises, ...parsedExercises.map((exercise, index) => ({
              ...exercise,
              order_index: day.exercises.length + index + 1
            }))]
      };
    });

    setPlan({ ...plan, days });
    setVisibleDayIds(Array.from(nextVisibleIds));
    const firstParsedDay = parsedDays[0];
    const firstDay = plan.days.find((day) => Number(day.day_number) === Number(firstParsedDay.day_number));
    if (firstDay) setActiveDayId(Number(firstDay.id));
    setParserPreview(null);
    setParserText('');
    notify(mode === 'replace' ? 'Preview applicata: day sostituiti' : 'Preview applicata: esercizi aggiunti');
  }

  function requestConfirmation(options) {
    setConfirmDialog(options);
  }

  async function confirmAction() {
    const action = confirmDialog?.onConfirm;
    setConfirmDialog(null);
    await action?.();
  }

  return (
    <section className="page print-scope">
      <div className="page-title row">
        <div>
          <h2>{showManualEditor ? <input className="title-input" value={plan.name} onChange={(e) => setPlan({ ...plan, name: e.target.value })} /> : plan.name}</h2>
          <p>{plan.assigned_user_name}</p>
        </div>
      </div>
      {editable && (
        <>
          {showManualEditor && canAssignAthletes && (
            <select className="wide-select" value={plan.assigned_user_id} onChange={(e) => setPlan({ ...plan, assigned_user_id: e.target.value })}>
              {athletes.map((item) => <option key={item.id} value={item.id}>{item.full_name}</option>)}
            </select>
          )}
          <section className="parser-panel no-print" aria-label="Workout Parser">
            <div className="parser-heading">
              <div>
                <h3><Wand2 size={19} /> Workout Parser</h3>
                <p>Scrivi qui l'allenamento e genera una preview prima di salvarlo nella scheda.</p>
              </div>
              <div className="parser-controls">
                <select
                  aria-label="Giorno destinazione parser"
                  value={parserTargetDayNumber}
                  onChange={(event) => setParserTargetDayNumber(Number(event.target.value))}
                  disabled={parserHasExplicitDays}
                >
                  {plan.days.map((day) => (
                    <option key={day.id} value={day.day_number}>{dayLabel(day.day_number)}</option>
                  ))}
                </select>
                <button className="primary" type="button" onClick={parseWorkoutText} disabled={parserLoading || parserText.trim() === ''}>
                  <FileText size={18} /> {parserLoading ? 'Analisi...' : 'Genera preview'}
                </button>
              </div>
            </div>
            <textarea
              className="parser-textarea"
              value={parserText}
              onChange={(event) => setParserText(event.target.value)}
              placeholder={'Scrivi l\'allenamento in testo libero.\n\nEsempio singolo esercizio:\nSquat 3x10 80kg recupero 2m\n\nPer inserire piu esercizi, separali con una virgola:\nSquat 3x10 80kg recupero 2m, pull up 3x10 30kg recupero 2m\n\nPuoi anche incollare messaggi WhatsApp o indicare Giorno 1, Giorno 2 o Day 1.'}
            />
            <div className="parser-examples" aria-label="Esempi supportati">
              <span>Squat 3x10 80kg recupero 2m</span>
              <span>Pull up 3x10 30kg recupero 2m</span>
              <span>Stacco 3x5 con 100kg</span>
              <span>Front lever 3s per 3 serie</span>
              <span>Giorno 2: panca 4x8 recupero 90s</span>
            </div>
            {parserPreview?.warnings?.length > 0 && (
              <div className="parser-warnings">
                {parserPreview.warnings.map((warning) => <span key={warning}>{warning}</span>)}
              </div>
            )}
            {!parserPreview?.days?.length && (
              <div className="parser-empty" aria-live="polite">
                <FileText size={22} />
                <div>
                  <strong>La preview della scheda apparirà qui dopo la generazione</strong>
                  <span>Inserisci il testo dell'allenamento, scegli il giorno e genera una preview prima di salvare.</span>
                </div>
              </div>
            )}
            {parserPreview?.days?.length > 0 && (
              <div className="parser-preview">
                <div className="parser-preview-head">
                  <strong>Preview modifiche</strong>
                  <span>Controlla i dati: campi ambigui restano vuoti.</span>
                </div>
                {getEffectiveParserDays().map((parsedDay) => {
                  const currentDay = plan.days.find((day) => Number(day.day_number) === Number(parsedDay.day_number));
                  const currentCount = currentDay?.exercises?.length || 0;
                  return (
                    <article className="parser-day" key={parsedDay.day_number}>
                      <div>
                        <strong>{formatDayTitle(parsedDay)}</strong>
                        <small>
                          Sostituisci: {currentCount} esercizi attuali diventano {countExpandedExercises(parsedDay.exercises)}. Aggiungi: +{countExpandedExercises(parsedDay.exercises)} esercizi.
                        </small>
                      </div>
                      <div className="parser-exercises">
                        {parsedDay.exercises.map((exercise, index) => {
                          const hasStructuredNotes = getStructuredNoteRows(exercise.notes).length > 1;
                          return (
                            <div className={['parser-exercise', hasStructuredNotes ? 'parser-exercise-structured' : ''].filter(Boolean).join(' ')} key={`${parsedDay.day_number}-${index}`}>
                              <strong>{exercise.name || 'Nome non riconosciuto'}</strong>
                              {!hasStructuredNotes && (
                                <>
                                  <span>Serie: {exercise.sets || '-'}</span>
                                  <span>Ripetizioni/durata: {exercise.reps || '-'}</span>
                                  <span>Peso: {exercise.weight || '-'}</span>
                                  <span>Recupero: {exercise.rest || '-'}</span>
                                </>
                              )}
                              {hasStructuredNotes && exercise.rest && <span className="parser-rest-only">Recupero: {exercise.rest}</span>}
                              {exercise.notes && <StructuredParserNotes notes={exercise.notes} />}
                            </div>
                          );
                        })}
                      </div>
                    </article>
                  );
                })}
                <div className="parser-actions">
                  <button className="ghost" type="button" onClick={() => applyParserPreview('append')}>Aggiungi alla scheda</button>
                  <button className="ghost danger" type="button" onClick={() => applyParserPreview('replace')}>Sostituisci day trovati</button>
                </div>
              </div>
            )}
          </section>
        </>
      )}
      {visibleDays.length > 1 && (
        <div className="day-tabs no-print" role="tablist" aria-label="Giorni allenamento">
          {visibleDays.map((day) => (
            <button
              key={day.id}
              type="button"
              role="tab"
              aria-selected={Number(day.id) === Number(activeDayId)}
              className={[
                Number(day.id) === Number(activeDayId) ? 'active' : '',
                !editable && Number(completedDayId) === Number(day.id) ? 'completed' : ''
              ].filter(Boolean).join(' ')}
              onClick={() => setActiveDayId(day.id)}
            >
              {dayLabel(day.day_number)}
            </button>
          ))}
        </div>
      )}
      {!editable && activeDay && (
        <AthleteWorkoutGuide
          activeDay={activeDay}
          completedDayId={completedDayId}
          position={activeDayPosition}
          total={visibleDays.length}
          onPrevious={() => {
            const previous = visibleDays[Math.max(0, activeDayPosition - 1)];
            if (previous) setActiveDayId(previous.id);
          }}
          onNext={() => {
            const next = visibleDays[Math.min(visibleDays.length - 1, activeDayPosition + 1)];
            if (next) setActiveDayId(next.id);
          }}
        />
      )}
      <div className="days">
        {daysToRender.map((day) => {
          const dayIndex = plan.days.findIndex((item) => Number(item.id) === Number(day.id));
          return (
          <article className={['day', (manualEditing || dirty) ? 'is-editing' : ''].filter(Boolean).join(' ')} key={day.id}>
            {editable && (
              <div className="current-plan-head no-print">
                <div>
                  <span>Scheda corrente</span>
                  <strong>{formatDayTitle(day)}</strong>
                </div>
                <div className="current-plan-toolbar">
                  <div className="editor-bottom-copy">
                    <strong>{manualEditing || dirty ? 'Modifiche non salvate' : 'Scheda salvata'}</strong>
                    <span>{manualEditing || dirty ? 'Salva le modifiche quando hai finito.' : 'Puoi modificare manualmente la scheda corrente.'}</span>
                  </div>
                  {manualEditing && <button className="ghost" onClick={cancelManualEditing}>Annulla</button>}
                  {!manualEditing && !dirty && <button className="ghost" onClick={startManualEditing}>Modifica</button>}
                  {(manualEditing || dirty) && <button className="primary" onClick={save}>Salva</button>}
                </div>
              </div>
            )}
            <div className="exercise-list">
              {editable && !showManualEditor && day.exercises.length === 0 && (
                <div className="current-plan-empty">
                  <strong>Nessun esercizio ancora</strong>
                  <span>La scheda comparirà qui dopo aver applicato una preview oppure dopo una modifica manuale.</span>
                </div>
              )}
              {day.exercises.map((exercise, exerciseIndex) => (
                showManualEditor ? (
                  <div className="exercise" key={`${day.id}-${exerciseIndex}`}>
                    <div className="exercise-name-cell">
                      <input placeholder={fieldLabel('name')} value={exercise.name || ''} onChange={(e) => updateExercise(dayIndex, exerciseIndex, { name: e.target.value })} />
                    </div>
                    <div className="exercise-compact-fields">
                      {['sets', 'reps', 'weight', 'rest'].map((field) => (
                        <input key={field} placeholder={fieldLabel(field)} value={exercise[field] || ''} onChange={(e) => updateExercise(dayIndex, exerciseIndex, { [field]: e.target.value })} />
                      ))}
                    </div>
                    <input className="exercise-notes-input" placeholder={fieldLabel('notes')} value={exercise.notes || ''} onChange={(e) => updateExercise(dayIndex, exerciseIndex, { notes: e.target.value })} />
                    <div className="exercise-actions no-print"><button className="ghost" onClick={() => duplicateExercise(dayIndex, exerciseIndex)}>Duplica</button><button className="ghost danger" onClick={() => removeExercise(dayIndex, exerciseIndex)}>Rimuovi</button></div>
                  </div>
                ) : editable ? (
                  <ReadOnlyExercise exercise={exercise} key={`${day.id}-${exerciseIndex}`} />
                ) : (
                  <div className="athlete-exercise" key={`${day.id}-${exerciseIndex}`}>
                    <div className="athlete-exercise-main">
                      <strong>{exercise.name}</strong>
                      <CoachExerciseValues exercise={exercise} />
                    </div>
                    <textarea
                      aria-label={`Note atleta per ${exercise.name}`}
                      placeholder="Annota sensazioni, carichi o varianti"
                      value={exercise.athlete_note || ''}
                      onChange={(e) => updateExercise(dayIndex, exerciseIndex, { athlete_note: e.target.value })}
                      onBlur={() => saveAthleteNote(exercise)}
                    />
                  </div>
                )
              ))}
            </div>
            {!editable && (
              <div className="day-completion day-completion-bottom no-print">
                {Number(completedDayId) === Number(day.id) && <span className="completion-status"><CheckCircle2 size={18} /> {formatDayTitle(day)} completato</span>}
                <button className="primary" onClick={() => completeWorkout(day)} disabled={Number(completedDayId) === Number(day.id) || completingDayId === day.id}>
                  <CheckCircle2 size={18} /> {Number(completedDayId) === Number(day.id) ? 'Giorno completo' : completingDayId === day.id ? 'Salvataggio...' : 'Completa giorno'}
                </button>
              </div>
            )}
          </article>
          );
        })}
      </div>
      {editable && (manualEditing || dirty) && (
        <div className="editor-bottom-actions no-print">
          {(manualEditing || dirty) && (
            <div className="editor-bottom-left">
              {daysToRender[0] && (
                <button
                  className="ghost danger"
                  onClick={() => removeDay(plan.days.findIndex((item) => Number(item.id) === Number(daysToRender[0].id)))}
                >
                  Elimina day
                </button>
              )}
              <button className="ghost danger" onClick={deletePlan}>Elimina programma</button>
            </div>
          )}
        </div>
      )}
      <ConfirmDialog dialog={confirmDialog} onCancel={() => setConfirmDialog(null)} onConfirm={confirmAction} />
    </section>
  );
}

function ConfirmDialog({ dialog, onCancel, onConfirm }) {
  if (!dialog) return null;

  return (
    <div className="confirm-overlay" role="presentation" onMouseDown={onCancel}>
      <div className="confirm-card" role="dialog" aria-modal="true" aria-labelledby="confirm-title" onMouseDown={(event) => event.stopPropagation()}>
        <div className={['confirm-icon', dialog.tone === 'danger' ? 'danger' : ''].filter(Boolean).join(' ')}>
          <AlertTriangle size={22} />
        </div>
        <div className="confirm-content">
          <h3 id="confirm-title">{dialog.title}</h3>
          <p>{dialog.message}</p>
        </div>
        <div className="confirm-actions">
          <button className="ghost" type="button" onClick={onCancel}>Annulla</button>
          <button className={dialog.tone === 'danger' ? 'danger-solid' : 'primary'} type="button" onClick={onConfirm}>
            {dialog.confirmLabel || 'Conferma'}
          </button>
        </div>
      </div>
    </div>
  );
}

function AthleteWorkoutGuide({ activeDay, completedDayId, position, total, onPrevious, onNext }) {
  const completed = Number(completedDayId) === Number(activeDay.id);
  const exerciseCount = activeDay.exercises?.length || 0;

  return (
    <section className="athlete-guide no-print" aria-label="Allenamento selezionato">
      <div>
        <span className="eyebrow">Allenamento di oggi</span>
        <h3>{formatDayTitle(activeDay)}</h3>
        <p>{exerciseCount} esercizi · Giorno {position + 1} di {total}</p>
      </div>
      <div className="athlete-guide-actions">
        <span className={completed ? 'guide-status completed' : 'guide-status'}>{completed ? 'Completato oggi' : 'Da completare'}</span>
        {total > 1 && (
          <div className="guide-nav">
            <button className="ghost" type="button" onClick={onPrevious} disabled={position === 0}>Precedente</button>
            <button className="ghost" type="button" onClick={onNext} disabled={position >= total - 1}>Prossimo</button>
          </div>
        )}
      </div>
    </section>
  );
}

function ReadOnlyExercise({ exercise }) {
  return (
    <div className="athlete-exercise coach-preview-exercise">
      <div className="athlete-exercise-main">
        <strong>{exercise.name}</strong>
        <CoachExerciseValues exercise={exercise} />
      </div>
    </div>
  );
}

function expandParsedExercise(exercise) {
  const rows = getStructuredNoteRows(exercise.notes);
  if (!rows.length) return [exercise];

  const expandedRows = rows.map((row) => ({
    ...exercise,
    sets: row.sets,
    reps: row.reps,
    weight: row.weight,
    notes: ''
  }));

  if (hasExerciseLoad(exercise) && !rows.some((row) => sameExerciseLoad(row, exercise))) {
    expandedRows.push({ ...exercise, notes: '' });
  }

  return expandedRows.sort((left, right) => numericSortValue(left.sets) - numericSortValue(right.sets));
}

function normalizePlanForManualEditing(plan) {
  return {
    ...plan,
    days: plan.days.map((day) => ({
      ...day,
      exercises: day.exercises.flatMap((exercise) => expandParsedExercise(exercise))
        .map((exercise, index) => ({ ...exercise, order_index: index + 1 }))
    }))
  };
}

function countExpandedExercises(exercises = []) {
  return exercises.reduce((total, exercise) => total + expandParsedExercise(exercise).length, 0);
}

function hasExerciseLoad(exercise) {
  return Boolean(exercise.sets || exercise.reps || exercise.weight || exercise.rest);
}

function sameExerciseLoad(row, exercise) {
  return normalizeLoadValue(row.sets) === normalizeLoadValue(exercise.sets)
    && normalizeLoadValue(row.reps) === normalizeLoadValue(exercise.reps)
    && normalizeLoadValue(row.weight) === normalizeLoadValue(exercise.weight);
}

function normalizeLoadValue(value) {
  return String(value || '').trim().toLowerCase().replace(/\s+/g, '');
}

function numericSortValue(value) {
  const number = Number(String(value || '').replace(',', '.'));
  return Number.isFinite(number) ? number : Number.MAX_SAFE_INTEGER;
}

function CoachExerciseValues({ exercise }) {
  const structuredRows = getStructuredNoteRows(exercise.notes);

  if (structuredRows.length > 1) {
    return (
      <div className="exercise-structured-notes">
        {structuredRows.map((row, index) => (
          <div className="exercise-structured-row" key={`${row.sets}-${row.reps}-${row.weight}-${index}`}>
            <div><small>Serie</small><strong>{row.sets}</strong></div>
            <div><small>Ripetizioni/durata</small><strong>{row.reps}</strong></div>
            <div><small>Peso</small><strong>{row.weight}</strong></div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="exercise-values">
      {exercise.sets && <span><small>Serie</small>{exercise.sets}</span>}
      {exercise.reps && <span><small>Ripetizioni</small>{exercise.reps}</span>}
      {exercise.weight && <span><small>Peso</small>{exercise.weight}</span>}
      {exercise.rest && <span><small>Recupero</small>{exercise.rest}</span>}
      {exercise.notes && <span className="exercise-note"><small>Note coach</small>{exercise.notes}</span>}
    </div>
  );
}

function StructuredParserNotes({ notes }) {
  const rows = getStructuredNoteRows(notes);

  if (rows.length < 2) {
    return <span className="parser-note">Note: {notes}</span>;
  }

  return (
    <div className="parser-note-table">
      {rows.map((row, index) => (
        <div key={`${row.sets}-${row.reps}-${row.weight}-${index}`}>
          <span>Serie: {row.sets}</span>
          <span>Ripetizioni/durata: {row.reps}</span>
          <span>Peso: {row.weight}</span>
        </div>
      ))}
    </div>
  );
}

function getStructuredNoteRows(notes = '') {
  return notes.split(/\n+/).map((line) => {
    const match = line.match(/Serie:\s*(\S+)\s+Rep:\s*(\S+)\s+Peso:\s*(\S+)/i);
    return match ? { sets: match[1], reps: match[2], weight: match[3] } : null;
  }).filter(Boolean);
}

function fieldLabel(field) {
  return { name: 'Esercizio', sets: 'Serie', reps: 'Ripetizioni', weight: 'Peso', rest: 'Recupero', notes: 'Note' }[field];
}

function dayLabel(dayNumber) {
  return `Giorno ${Number(dayNumber) || 1}`;
}

function formatDayTitle(day) {
  const rawTitle = String(day?.title || day?.name || '').trim();
  const dayNumber = Number(day?.day_number) || 1;
  if (/^(day|giorno)\s*\d+$/i.test(rawTitle)) {
    return dayLabel(dayNumber);
  }
  return rawTitle || dayLabel(dayNumber);
}

function isToday(value) {
  if (!value) return false;
  const date = new Date(value.replace(' ', 'T'));
  const today = new Date();
  return date.getFullYear() === today.getFullYear()
    && date.getMonth() === today.getMonth()
    && date.getDate() === today.getDate();
}
