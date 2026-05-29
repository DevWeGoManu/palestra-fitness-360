# Copilot Session Log

Data: 2026-05-29

## Descrizione della modifica

Aggiornamento automatico del log di sessione per includere il diff completo delle modifiche attuali. Il contenuto è stato estratto con `git diff HEAD~1` per permettere la ricostruzione futura di ogni modifica senza dover consultare la cronologia della chat.

## Diff attuale

```diff
diff --git a/frontend/package.json b/frontend/package.json
index 6a7e049..d12a9d1 100644
--- a/frontend/package.json
+++ b/frontend/package.json
@@ -6,7 +6,8 @@
   "scripts": {
     "dev": "vite",
     "build": "vite build && node scripts/copy-public-dotfiles.mjs",
-    "preview": "vite preview"
+    "preview": "vite preview",
+    "test:smoke": "npx playwright test --config=playwright.config.ts"
   },
   "dependencies": {
     "@vitejs/plugin-react": "^5.0.0",
@@ -15,5 +16,10 @@
     "react-dom": "^19.0.0",
     "lucide-react": "^0.468.0"
   },
-  "devDependencies": {}
+  "devDependencies": {
+    "@playwright/test": "^1.40.0"
+  },
+  "playwright": {
+    "browsers": ["chromium"]
+  }
 }
diff --git a/frontend/src/components/Shell.jsx b/frontend/src/components/Shell.jsx
index 688e728..6d56d02 100644
--- a/frontend/src/components/Shell.jsx
+++ b/frontend/src/components/Shell.jsx
@@ -6,6 +6,7 @@ export function Shell({ user, onLogout, children }) {
   const [open, setOpen] = React.useState(false);
   const currentPath = (window.location.hash.replace(/^#/, '').split('?')[0] || '/');
   const logoSrc = `${import.meta.env.BASE_URL}icons/icon-192.png`;
+  const roleLabel = user.role?.charAt(0).toUpperCase() + user.role?.slice(1);
   const nav = canManage(user)
     ? [
         { href: '/users', label: 'Utenti', icon: Users },
@@ -34,6 +35,10 @@ export function Shell({ user, onLogout, children }) {
           <img src={logoSrc} alt="" aria-hidden="true" />
           <strong>AthleoDesk</strong>
         </div>
+        <div className="sidebar-meta">
+          <strong>{user.full_name}</strong>
+          <span>{roleLabel}</span>
+        </div>
         <nav aria-label="Navigazione principale">
           {nav.map((item) => {
             const Icon = item.icon;
@@ -53,30 +58,38 @@ export function Shell({ user, onLogout, children }) {
             );
           })}
         </nav>
-        <button
-          type="button"
-          className={currentPath === '/tickets' ? 'active sidebar-ticket' : 'sidebar-ticket'}
-          aria-current={currentPath === '/tickets' ? 'page' : undefined}
-          aria-label="Ticket"
-          onClick={() => { go('/tickets'); setOpen(false); }}
-        >
-          <LifeBuoy size={19} /> Ticket
-        </button>
-        <button type="button" className="ghost logout" aria-label="Esci" onClick={onLogout}><LogOut size={18} /> Esci</button>
-      </aside>
-      <div className="main-area">
-        <header className="topbar">
+        <div className="sidebar-actions">
           <button
             type="button"
-            className="icon-button mobile-only"
-            onClick={() => setOpen(true)}
-            aria-label="Apri menu"
-            aria-controls="primary-sidebar"
-            aria-expanded={open}
+            className={currentPath === '/tickets' ? 'active sidebar-ticket' : 'sidebar-ticket'}
+            aria-current={currentPath === '/tickets' ? 'page' : undefined}
+            aria-label="Ticket"
+            onClick={() => { go('/tickets'); setOpen(false); }}
           >
-            <Menu />
+            <LifeBuoy size={19} /> Ticket
           </button>
-          <div><strong>{user.full_name}</strong><span>{user.role}</span></div>
+          <button type="button" className="ghost logout" aria-label="Esci" onClick={onLogout}><LogOut size={18} /> Esci</button>
+        </div>
+      </aside>
+      <div className="main-area">
+        <header className="topbar">
+          <div className="topbar-left">
+            <button
+              type="button"
+              className="icon-button mobile-only"
+              onClick={() => setOpen(true)}
+              aria-label="Apri menu"
+              aria-controls="primary-sidebar"
+              aria-expanded={open}
+            >
+              <Menu />
+            </button>
+            <span className="topbar-page-label">Pannello di controllo</span>
+          </div>
+          <div className="topbar-user">
+            <strong>{user.full_name}</strong>
+            <span>{roleLabel}</span>
+          </div>
         </header>
         {open && <button type="button" className="overlay" onClick={() => setOpen(false)} aria-label="Chiudi menu"><X /></button>}
         {children}
diff --git a/frontend/src/pages/Dashboard.jsx b/frontend/src/pages/Dashboard.jsx
index f2dbe12..90877e7 100644
--- a/frontend/src/pages/Dashboard.jsx
+++ b/frontend/src/pages/Dashboard.jsx
@@ -29,7 +29,12 @@ export function Dashboard({ user }) {
                 <span>{new Date(session.completed_at).toLocaleString('it-IT')}</span>
               </div>
             ))}
-            {(stats?.recent_sessions || []).length === 0 && <p className="empty">Nessun allenamento completato.</p>}
+            {(stats?.recent_sessions || []).length === 0 && (
+              <div className="empty empty-card">
+                <strong>Nessun allenamento completato</strong>
+                <span>Non ci sono sessioni recenti da mostrare.</span>
+              </div>
+            )}
           </div>
         </>
       )}
diff --git a/frontend/src/pages/PlanEditor.jsx b/frontend/src/pages/PlanEditor.jsx
index 3904eb5..ffc80a5 100644
--- a/frontend/src/pages/PlanEditor.jsx
+++ b/frontend/src/pages/PlanEditor.jsx
@@ -1,10 +1,11 @@
-import { useEffect, useMemo, useState } from 'react';
-import { AlertTriangle, CheckCircle2, FileText, Wand2 } from 'lucide-react';
+import { useEffect, useMemo, useRef, useState } from 'react';
+import { AlertTriangle, CheckCircle2, FileText, Info, Wand2 } from 'lucide-react';
 import { api } from '../api.js';
 import { Loading } from '../components/Loading.jsx';
 import { canEditWorkouts, canManage, go } from '../utils/router.js';
 
 const emptyExercise = { type: 'exercise', name: '', sets: '', reps: '', weight: '', rest: '', notes: '', order_index: 1 };
+const SKIP_APPEND_CONFIRM_KEY = 'athleo.skipAppendWorkoutConfirm';
 
 export function PlanEditor({ id, user, notify, editMode = false }) {
   const [plan, setPlan] = useState(null);
@@ -23,6 +24,7 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
   const [manualEditing, setManualEditing] = useState(false);
   const [editSnapshot, setEditSnapshot] = useState('');
   const [confirmDialog, setConfirmDialog] = useState(null);
+  const [lastSavedAt, setLastSavedAt] = useState(null);
   const editable = canManage(user) || (editMode && canEditWorkouts(user));
   const canAssignAthletes = canManage(user);
   const showManualEditor = editable && manualEditing;
@@ -34,6 +36,7 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
       const data = await api.plan(id);
       setPlan(data.plan);
       setSavedSnapshot(JSON.stringify(data.plan));
+      setLastSavedAt(new Date());
       setManualEditing(false);
       const daysWithExercises = data.plan.days.filter((day) => day.exercises.length > 0);
       const firstActiveDay = daysWithExercises[0] || data.plan.days[0];
@@ -89,6 +92,25 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
     }));
   }
 
+  function getParserActionState() {
+    const parsedDays = getEffectiveParserDays();
+    const replaceableDays = parserHasExplicitDays
+      ? parsedDays
+          .map((parsedDay) => plan.days.find((day) => Number(day.day_number) === Number(parsedDay.day_number)))
+          .filter((day) => day?.exercises?.length > 0)
+      : [];
+    const updateMatches = countParserUpdateMatches(parsedDays, plan.days);
+
+    return {
+      replaceableDays,
+      replaceLabel: replaceableDays.length === 1
+        ? `Sostituisci ${formatDayTitle(replaceableDays[0])}`
+        : `Sostituisci ${replaceableDays.length} giorni`,
+      updateMatches,
+      updateLabel: `Aggiorna ${updateMatches} ${updateMatches === 1 ? 'blocco' : 'blocchi'}`
+    };
+  }
+
   function updateDay(dayIndex, patch) {
     setPlan({ ...plan, days: plan.days.map((day, index) => index === dayIndex ? { ...day, ...patch } : day) });
   }
@@ -195,6 +217,7 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
       const data = await api.savePlan(plan.id, plan);
       setPlan(data.plan);
       setSavedSnapshot(JSON.stringify(data.plan));
+      setLastSavedAt(new Date());
       notify('Programma salvato');
       go(canManage(user) ? '/workouts' : `/plan?id=${data.plan.id}`);
     } catch (err) {
@@ -260,16 +283,26 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
     const parsedDays = getEffectiveParserDays();
     if (!parsedDays.length) return;
 
+    if (mode === 'append' && shouldSkipAppendConfirm()) {
+      applyParserDays(parsedDays, mode);
+      return;
+    }
+
     const isReplace = mode === 'replace';
     const isUpdate = mode === 'update';
     const action = isReplace ? 'sostituire' : isUpdate ? 'aggiornare dentro' : 'aggiungere a';
     const dayNames = parsedDays.map((day) => formatDayTitle(day)).join(', ');
+    const appendMessage = parsedDays.length === 1
+      ? `Gli esercizi verranno aggiunti a ${formatDayTitle(parsedDays[0])}.\nRicorda di salvare la scheda per rendere permanenti le modifiche.`
+      : `Gli esercizi verranno aggiunti a ${dayNames}.\nRicorda di salvare la scheda per rendere permanenti le modifiche.`;
 
     requestConfirmation({
       title: isReplace ? 'Sostituire i day trovati?' : isUpdate ? 'Aggiornare i blocchi corrispondenti?' : 'Aggiungere alla scheda?',
-      message: `Vuoi ${action} ${dayNames} nella scheda corrente? Dovrai comunque premere Salva per rendere definitive le modifiche.`,
+      message: mode === 'append' ? appendMessage : `Vuoi ${action} ${dayNames} nella scheda corrente? Dovrai comunque premere Salva per rendere definitive le modifiche.`,
       confirmLabel: isReplace ? 'Sostituisci' : isUpdate ? 'Aggiorna' : 'Aggiungi',
       tone: isReplace ? 'danger' : 'default',
+      preferenceKey: mode === 'append' ? SKIP_APPEND_CONFIRM_KEY : '',
+      preferenceLabel: mode === 'append' ? 'Non mostrare pi├╣ questo avviso' : '',
       onConfirm: () => applyParserDays(parsedDays, mode)
     });
   }
@@ -380,28 +413,32 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
     setConfirmDialog(options);
   }
 
-  async function confirmAction() {
+  async function confirmAction(options = {}) {
     const action = confirmDialog?.onConfirm;
+    if (confirmDialog?.preferenceKey && options.rememberPreference) {
+      localStorage.setItem(confirmDialog.preferenceKey, '1');
+    }
     setConfirmDialog(null);
     await action?.();
   }
 
   return (
     <section className="page print-scope">
-      <div className="page-title row">
-        <div>
-          <h2>{showManualEditor ? <input className="title-input" value={plan.name} onChange={(e) => setPlan({ ...plan, name: e.target.value })} /> : plan.name}</h2>
-          {normalizeExerciseName(plan.name) !== normalizeExerciseName(plan.assigned_user_name) && <p>{plan.assigned_user_name}</p>}
+      <div className="plan-editor-flow">
+        <div className="page-title row">
+          <div>
+            <h2>{showManualEditor ? <input className="title-input" value={plan.name} onChange={(e) => setPlan({ ...plan, name: e.target.value })} /> : plan.name}</h2>
+            {normalizeExerciseName(plan.name) !== normalizeExerciseName(plan.assigned_user_name) && <p>{plan.assigned_user_name}</p>}
+          </div>
         </div>
-      </div>
-      {editable && (
-        <>
-          {showManualEditor && canAssignAthletes && (
-            <select className="wide-select" value={plan.assigned_user_id} onChange={(e) => setPlan({ ...plan, assigned_user_id: e.target.value })}>
-              {athletes.map((item) => <option key={item.id} value={item.id}>{item.full_name}</option>)}
-            </select>
-          )}
-          <section className="parser-panel no-print" aria-label="Workout Parser">
+        {editable && (
+          <>
+            {showManualEditor && canAssignAthletes && (
+              <select className="wide-select" value={plan.assigned_user_id} onChange={(e) => setPlan({ ...plan, assigned_user_id: e.target.value })}>
+                {athletes.map((item) => <option key={item.id} value={item.id}>{item.full_name}</option>)}
+              </select>
+            )}
+            <section className="parser-panel no-print" aria-label="Workout Parser">
             <div className="parser-heading">
               <div>
                 <h3><Wand2 size={19} /> Workout Parser</h3>
@@ -452,6 +489,11 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
                 <div>
                   <strong>La preview della scheda apparir├á qui dopo la generazione</strong>
                   <span>Inserisci il testo dell'allenamento, scegli il giorno e genera una preview prima di salvare.</span>
+                  <div className="parser-empty-examples" aria-label="Mini esempi parser">
+                    <code>Squat 3x10 con 80kg</code>
+                    <code>1 Mu / 12 Bar Dip / x4</code>
+                    <code>Giorno 2: Panca 4x8</code>
+                  </div>
                 </div>
               </div>
             )}
@@ -498,22 +540,21 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
                     </article>
                   );
                 })}
-                <div className="parser-actions">
-                  <button className="ghost" type="button" onClick={() => applyParserPreview('append')}>Aggiungi alla scheda</button>
-                  <button className="ghost danger" type="button" onClick={() => applyParserPreview('replace')}>Sostituisci day trovati</button>
-                  {parserEditTarget ? (
-                    <button className="primary" type="button" onClick={applyParserEditTargetPreview}>Aggiorna questo blocco</button>
-                  ) : (
-                    <button className="ghost" type="button" onClick={() => applyParserPreview('update')}>Aggiorna blocchi corrispondenti</button>
-                  )}
-                </div>
+                <ParserActions
+                  actionState={getParserActionState()}
+                  isTargetEditing={Boolean(parserEditTarget)}
+                  onAppend={() => applyParserPreview('append')}
+                  onReplace={() => applyParserPreview('replace')}
+                  onUpdate={() => applyParserPreview('update')}
+                  onTargetUpdate={applyParserEditTargetPreview}
+                />
               </div>
             )}
-          </section>
-        </>
-      )}
-      {visibleDays.length > 1 && (
-        <div className="day-tabs no-print" role="tablist" aria-label="Giorni allenamento">
+            </section>
+          </>
+        )}
+        {visibleDays.length > 1 && (
+          <div className="day-tabs no-print" role="tablist" aria-label="Giorni allenamento">
           {visibleDays.map((day) => (
             <button
               key={day.id}
@@ -526,13 +567,14 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
               ].filter(Boolean).join(' ')}
               onClick={() => setActiveDayId(day.id)}
             >
-              {dayLabel(day.day_number)}
+              <span>{dayLabel(day.day_number)}</span>
+              <small>{day.exercises.length} es. ┬À {dayTabStatus(day, dirty || manualEditing)}</small>
             </button>
           ))}
-        </div>
-      )}
-      {!editable && activeDay && (
-        <AthleteWorkoutGuide
+          </div>
+        )}
+        {!editable && activeDay && (
+          <AthleteWorkoutGuide
           activeDay={activeDay}
           completedDayId={completedDayId}
           position={activeDayPosition}
@@ -545,9 +587,9 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
             const next = visibleDays[Math.min(visibleDays.length - 1, activeDayPosition + 1)];
             if (next) setActiveDayId(next.id);
           }}
-        />
-      )}
-      <div className="days">
+          />
+        )}
+        <div className="days">
         {daysToRender.map((day) => {
           const dayIndex = plan.days.findIndex((item) => Number(item.id) === Number(day.id));
           return (
@@ -560,8 +602,8 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
                 </div>
                 <div className="current-plan-toolbar">
                   <div className="editor-bottom-copy">
-                    <strong>{manualEditing || dirty ? 'Modifiche non salvate' : 'Scheda salvata'}</strong>
-                    <span>{manualEditing || dirty ? 'Salva le modifiche quando hai finito.' : 'Puoi modificare manualmente la scheda corrente.'}</span>
+                    <strong className={manualEditing || dirty ? 'status-unsaved' : 'status-saved'}>{manualEditing || dirty ? 'Non salvata' : 'Salvata'}</strong>
+                    <span>{manualEditing || dirty ? 'Modifiche da salvare' : `Ultimo salvataggio: ${formatSavedTime(lastSavedAt)}`}</span>
                   </div>
                   {manualEditing && <button className="ghost" onClick={cancelManualEditing}>Annulla</button>}
                   {!manualEditing && !dirty && <button className="ghost" onClick={startManualEditing}>Modifica</button>}
@@ -648,9 +690,9 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
           </article>
           );
         })}
-      </div>
-      {editable && (manualEditing || dirty) && (
-        <div className="editor-bottom-actions no-print">
+        </div>
+        {editable && (manualEditing || dirty) && (
+          <div className="editor-bottom-actions no-print">
           {(manualEditing || dirty) && (
             <div className="editor-bottom-left">
               {daysToRender[0] && (
@@ -664,29 +706,77 @@ export function PlanEditor({ id, user, notify, editMode = false }) {
               <button className="ghost danger" onClick={deletePlan}>Elimina programma</button>
             </div>
           )}
-        </div>
-      )}
+          </div>
+        )}
+      </div>
       <ConfirmDialog dialog={confirmDialog} onCancel={() => setConfirmDialog(null)} onConfirm={confirmAction} />
     </section>
   );
 }
 
 function ConfirmDialog({ dialog, onCancel, onConfirm }) {
+  const cardRef = useRef(null);
+  const [rememberPreference, setRememberPreference] = useState(false);
+
+  useEffect(() => {
+    if (!dialog) return undefined;
+    setRememberPreference(false);
+
+    const card = cardRef.current;
+    const focusable = getFocusableElements(card);
+    focusable[0]?.focus();
+
+    const handleKeyDown = (event) => {
+      if (event.key === 'Escape') {
+        event.preventDefault();
+        onCancel();
+        return;
+      }
+
+      if (event.key !== 'Tab' || focusable.length === 0) return;
+
+      const first = focusable[0];
+      const last = focusable[focusable.length - 1];
+      if (event.shiftKey && document.activeElement === first) {
+        event.preventDefault();
+        last.focus();
+      } else if (!event.shiftKey && document.activeElement === last) {
+        event.preventDefault();
+        first.focus();
+      }
+    };
+
+    document.addEventListener('keydown', handleKeyDown);
+    return () => document.removeEventListener('keydown', handleKeyDown);
+  }, [dialog, onCancel]);
+
   if (!dialog) return null;
 
+  const Icon = dialog.tone === 'danger' ? AlertTriangle : Info;
+
   return (
     <div className="confirm-overlay" role="presentation" onMouseDown={onCancel}>
-      <div className="confirm-card" role="dialog" aria-modal="true" aria-labelledby="confirm-title" onMouseDown={(event) => event.stopPropagation()}>
-        <div className={['confirm-icon', dialog.tone === 'danger' ? 'danger' : ''].filter(Boolean).join(' ')}>
-          <AlertTriangle size={22} />
+      <div className="confirm-card" role="dialog" aria-modal="true" aria-labelledby="confirm-title" ref={cardRef} onMouseDown={(event) => event.stopPropagation()}>
+        <div className={['confirm-icon', dialog.tone === 'danger' ? 'danger' : 'info'].filter(Boolean).join(' ')}>
+          <Icon size={22} />
         </div>
         <div className="confirm-content">
           <h3 id="confirm-title">{dialog.title}</h3>
           <p>{dialog.message}</p>
+          {dialog.preferenceLabel && (
+            <label className="confirm-preference">
+              <input
+                type="checkbox"
+                checked={rememberPreference}
+                onChange={(event) => setRememberPreference(event.target.checked)}
+              />
+              <span>{dialog.preferenceLabel}</span>
+            </label>
+          )}
         </div>
         <div className="confirm-actions">
           <button className="ghost" type="button" onClick={onCancel}>Annulla</button>
-          <button className={dialog.tone === 'danger' ? 'danger-solid' : 'primary'} type="button" onClick={onConfirm}>
+          <button className={dialog.tone === 'danger' ? 'danger-solid' : 'primary'} type="button" onClick={() => onConfirm({ rememberPreference })}>
             {dialog.confirmLabel || 'Conferma'}
           </button>
         </div>
@@ -695,6 +785,32 @@ function ConfirmDialog({ dialog, onCancel, onConfirm }) {
   );
 }
 
+function getFocusableElements(container) {
+  if (!container) return [];
+  return Array.from(container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
+    .filter((element) => !element.disabled && element.getAttribute('aria-hidden') !== 'true');
+}
+
+function ParserActions({ actionState, isTargetEditing, onAppend, onReplace, onUpdate, onTargetUpdate }) {
+  return (
+    <div className="parser-actions">
+      <button className="ghost" type="button" onClick={onAppend}>Aggiungi alla scheda</button>
+      {actionState.replaceableDays.length > 0 && (
+        <button className="ghost danger" type="button" onClick={onReplace}>{actionState.replaceLabel}</button>
+      )}
+      {isTargetEditing ? (
+        <button className="primary" type="button" onClick={onTargetUpdate}>Aggiorna questo blocco</button>
+      ) : actionState.updateMatches > 0 && (
+        <button className="ghost" type="button" onClick={onUpdate}>{actionState.updateLabel}</button>
+      )}
+    </div>
+  );
+}
+
+function shouldSkipAppendConfirm() {
+  return localStorage.getItem(SKIP_APPEND_CONFIRM_KEY) === '1';
+}
+
 function AthleteWorkoutGuide({ activeDay, completedDayId, position, total, onPrevious, onNext }) {
   const completed = Number(completedDayId) === Number(activeDay.id);
   const exerciseCount = activeDay.exercises?.length || 0;
@@ -851,6 +967,24 @@ function findParserEditTargetBlockIndex(exercises = [], target) {
   return target.blockIndex >= 0 && target.blockIndex < exercises.length ? target.blockIndex : -1;
 }
 
+function countParserUpdateMatches(parsedDays = [], currentDays = []) {
+  return parsedDays.reduce((total, parsedDay) => {
+    const currentDay = currentDays.find((day) => Number(day.day_number) === Number(parsedDay.day_number));
+    if (!currentDay?.exercises?.length) return total;
+
+    const matchedIndexes = new Set();
+    const parsedExercises = (parsedDay.exercises || []).flatMap((exercise) => expandParsedExercise(exercise));
+    const dayMatches = parsedExercises.reduce((count, parsedExercise) => {
+      const matchIndex = findMatchingExerciseIndex(currentDay.exercises, parsedExercise, matchedIndexes);
+      if (matchIndex < 0) return count;
+      matchedIndexes.add(matchIndex);
+      return count + 1;
+    }, 0);
+
+    return total + dayMatches;
+  }, 0);
+}
+
 function applyParsedExercisesToDay(currentExercises = [], parsedExercises = [], mode) {
   if (mode === 'replace') return parsedExercises;
 
@@ -944,6 +1078,25 @@ function numericSortValue(value) {
   return Number.isFinite(number) ? number : Number.MAX_SAFE_INTEGER;
 }
 
+function dayTabStatus(day, hasUnsavedChanges) {
+  if (!day.exercises?.length) return 'vuoto';
+  return hasUnsavedChanges ? 'modificato' : 'salvato';
+}
+
+function formatSavedTime(date) {
+  if (!date) return 'ora';
+  return date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
+}
+
+function formatExerciseInlineMeta(exercise) {
+  return [
+    exercise.sets ? `${exercise.sets} serie` : '',
+    exercise.reps ? `${exercise.reps} reps` : '',
+    exercise.weight || '',
+    exercise.rest ? `rec. ${exercise.rest}` : ''
+  ].filter(Boolean).join(' ┬À ');
+}
+
 function CoachExerciseValues({ exercise }) {
   if (isCircuitBlock(exercise)) {
     return <CircuitSummary block={exercise} />;
@@ -965,12 +1118,11 @@ function CoachExerciseValues({ exercise }) {
     );
   }
 
+  const inlineMeta = formatExerciseInlineMeta(exercise);
+
   return (
-    <div className="exercise-values">
-      {exercise.sets && <span><small>Serie</small>{exercise.sets}</span>}
-      {exercise.reps && <span><small>Ripetizioni</small>{exercise.reps}</span>}
-      {exercise.weight && <span><small>Peso</small>{exercise.weight}</span>}
-      {exercise.rest && <span><small>Recupero</small>{exercise.rest}</span>}
+    <div className="exercise-values compact">
+      {inlineMeta && <span className="exercise-inline-meta">{inlineMeta}</span>}
       {exercise.notes && <span className="exercise-note"><small>Note coach</small>{exercise.notes}</span>}
     </div>
   );
diff --git a/frontend/src/pages/Tickets.jsx b/frontend/src/pages/Tickets.jsx
index baf3e85..4472efd 100644
--- a/frontend/src/pages/Tickets.jsx
+++ b/frontend/src/pages/Tickets.jsx
@@ -53,12 +53,12 @@ export function Tickets({ notify }) {
 
   return (
     <section className="page">
-      <div className="page-title ticket-title">
-        <span className="ticket-title-icon"><TicketCheck size={22} /></span>
+      <div className="page-title row ticket-title">
         <div>
           <h2>Ticket</h2>
           <p>Segnala un malfunzionamento del sito.</p>
         </div>
+        <span className="ticket-title-icon"><TicketCheck size={22} /></span>
       </div>
 
       <form className="ticket-panel" onSubmit={submit}>
diff --git a/frontend/src/pages/UserDetail.jsx b/frontend/src/pages/UserDetail.jsx
index 496ae91..e4c0d23 100644
--- a/frontend/src/pages/UserDetail.jsx
+++ b/frontend/src/pages/UserDetail.jsx
@@ -105,6 +105,21 @@ export function UserDetail({ id, currentUser, onUserUpdate, notify }) {
         </div>
       </div>
 
+      <div className="user-detail-actions row">
+        <div className="user-detail-badges">
+          <span className={`badge role-badge`}>{roleLabels[data.user.role] || data.user.role}</span>
+          <span className={`badge status-${data.user.status}`}>{statusLabels[data.user.status] || data.user.status}</span>
+        </div>
+        <div className="user-detail-buttons">
+          {canSeeProgramming && (
+            <button className="primary" type="button" onClick={data.plans.length > 0 ? () => go(`/plan?id=${data.plans[0].id}`) : createPlan}>
+              <CalendarDays size={18} /> {data.plans.length > 0 ? 'Apri scheda' : 'Crea scheda'}
+            </button>
+          )}
+          {canDeleteUser && <button className="ghost danger" onClick={remove}><Trash2 size={17} /> Elimina utente</button>}
+        </div>
+      </div>
+
       <section className="user-info-card" aria-label="Informazioni utente">
         <div className="user-info-main">
           <span className="user-info-avatar" aria-hidden="true">{getInitials(data.user.full_name)}</span>
@@ -141,21 +156,33 @@ export function UserDetail({ id, currentUser, onUserUpdate, notify }) {
 
       {canEditProfile && (
         <form className="panel-form" onSubmit={save}>
-          <input value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} required />
-          <input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required />
+          <label>
+            <span>Nome completo</span>
+            <input value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} required />
+          </label>
+          <label>
+            <span>Email</span>
+            <input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required />
+          </label>
           {isCurrentAdmin ? (
             <>
-              <select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
-                <option value="atleta">Atleta</option>
-                <option value="autonomo">Autonomo</option>
-                <option value="istruttore">Istruttore</option>
-                <option value="admin">Admin</option>
-              </select>
-              <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
-                <option value="pending">Pending</option>
-                <option value="active">Active</option>
-                <option value="disabled">Disabled</option>
-              </select>
+              <label>
+                <span>Ruolo</span>
+                <select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
+                  <option value="atleta">Atleta</option>
+                  <option value="autonomo">Autonomo</option>
+                  <option value="istruttore">Istruttore</option>
+                  <option value="admin">Admin</option>
+                </select>
+              </label>
+              <label>
+                <span>Status</span>
+                <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
+                  <option value="pending">Pending</option>
+                  <option value="active">Active</option>
+                  <option value="disabled">Disabled</option>
+                </select>
+              </label>
             </>
           ) : null}
           <button className="primary">Salva utente</button>
@@ -174,7 +201,12 @@ export function UserDetail({ id, currentUser, onUserUpdate, notify }) {
           <h3>Schede assegnate</h3>
           <div className="list">
             {data.plans.map((plan) => <button key={plan.id} className="list-row" onClick={() => go(`/plan?id=${plan.id}`)}><strong>{plan.name}</strong><span>{new Date(plan.created_at).toLocaleDateString('it-IT')}</span></button>)}
-            {data.plans.length === 0 && <p className="empty">Nessuna scheda assegnata.</p>}
+            {data.plans.length === 0 && (
+              <div className="empty empty-card">
+                <strong>Nessuna scheda assegnata</strong>
+                <span>Il profilo non ha ancora programmi collegati.</span>
+              </div>
+            )}
           </div>
         </>
       )}
diff --git a/frontend/src/pages/UsersPage.jsx b/frontend/src/pages/UsersPage.jsx
index bcde881..7301f1b 100644
--- a/frontend/src/pages/UsersPage.jsx
+++ b/frontend/src/pages/UsersPage.jsx
@@ -1,5 +1,5 @@
 import { useEffect, useState } from 'react';
-import { Search } from 'lucide-react';
+import { Search, X } from 'lucide-react';
 import { api } from '../api.js';
 import { Loading } from '../components/Loading.jsx';
 import { go } from '../utils/router.js';
@@ -22,8 +22,8 @@ export function UsersPage({ notify }) {
   const [users, setUsers] = useState([]);
   const [loading, setLoading] = useState(true);
   const [statusFilter, setStatusFilter] = useState('');
-  const [searchInput, setSearchInput] = useState('');
-  const [search, setSearch] = useState('');
+  const [searchQuery, setSearchQuery] = useState('');
+  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState('');
 
   async function load() {
     setLoading(true);
@@ -38,24 +38,28 @@ export function UsersPage({ notify }) {
   }
 
   useEffect(() => { load(); }, []);
-
-  function applySearch(event) {
-    event.preventDefault();
-    setSearch(searchInput.trim().toLowerCase());
-  }
+  useEffect(() => {
+    const timeoutId = window.setTimeout(() => setDebouncedSearchQuery(searchQuery.trim().toLowerCase()), 280);
+    return () => window.clearTimeout(timeoutId);
+  }, [searchQuery]);
 
   const filteredUsers = users.filter((item) => {
     const matchesStatus = !statusFilter || item.status === statusFilter;
-    const haystack = `${item.full_name} ${item.email} ${item.role} ${item.status}`.toLowerCase();
-    const matchesSearch = !search || haystack.includes(search);
+    const haystack = `${item.full_name} ${item.email} ${item.role}`.toLowerCase();
+    const matchesSearch = !debouncedSearchQuery || haystack.includes(debouncedSearchQuery);
     return matchesStatus && matchesSearch;
   });
 
   return (
     <section className="page">
-      <div className="page-title">
-        <h2>Utenti</h2>
-        <p>Gestisci profili, status e programmazione degli atleti.</p>
+      <div className="page-title row">
+        <div>
+          <h2>Utenti</h2>
+          <p>Gestisci profili, status e programmazione degli atleti.</p>
+        </div>
+        <div className="users-summary" aria-live="polite">
+          {loading ? 'Caricamento utentiÔÇª' : `${filteredUsers.length} su ${users.length} utenti`}
+        </div>
       </div>
 
       <div className="users-controls">
@@ -73,16 +77,24 @@ export function UsersPage({ notify }) {
           ))}
         </div>
 
-        <form className="users-search" onSubmit={applySearch}>
+        <div className="users-search" role="search">
           <label className="sr-only" htmlFor="users-search">Cerca utenti</label>
-          <input
-            id="users-search"
-            placeholder="Cerca per nome, email o ruolo"
-            value={searchInput}
-            onChange={(e) => setSearchInput(e.target.value)}
-          />
-          <button className="primary" type="submit"><Search size={18} /> Cerca</button>
-        </form>
+          <div className="users-search-field">
+            <Search size={17} aria-hidden="true" />
+            <input
+              id="users-search"
+              placeholder="Cerca per nome, email o ruolo"
+              value={searchQuery}
+              onChange={(e) => setSearchQuery(e.target.value)}
+            />
+            {searchQuery && (
+              <button className="users-search-clear" type="button" onClick={() => setSearchQuery('')} aria-label="Pulisci ricerca">
+                <X size={15} />
+              </button>
+            )}
+          </div>
+          {searchQuery && <button className="ghost" type="button" onClick={() => setSearchQuery('')}>Pulisci</button>}
+        </div>
       </div>
 
       {loading ? <Loading /> : (
diff --git a/frontend/src/pages/Workouts.jsx b/frontend/src/pages/Workouts.jsx
index 7970e54..585b2c8 100644
--- a/frontend/src/pages/Workouts.jsx
+++ b/frontend/src/pages/Workouts.jsx
@@ -1,4 +1,4 @@
-import { useEffect, useState } from 'react';
+import { useEffect, useMemo, useState } from 'react';
 import { CalendarDays, Plus, Trash2 } from 'lucide-react';
 import { api } from '../api.js';
 import { Loading } from '../components/Loading.jsx';
@@ -8,7 +8,21 @@ export function Workouts({ user, notify }) {
   const [plans, setPlans] = useState([]);
   const [users, setUsers] = useState([]);
   const [newPlan, setNewPlan] = useState({ assigned_user_id: '' });
+  const [athleteQuery, setAthleteQuery] = useState('');
+  const [athleteMenuOpen, setAthleteMenuOpen] = useState(false);
+  const [activeAthleteIndex, setActiveAthleteIndex] = useState(0);
   const [loading, setLoading] = useState(true);
+  const athletes = useMemo(() => users.filter((u) => ['atleta', 'autonomo'].includes(u.role)), [users]);
+  const selectedAthlete = useMemo(() => athletes.find((item) => Number(item.id) === Number(newPlan.assigned_user_id)), [athletes, newPlan.assigned_user_id]);
+  const filteredAthletes = useMemo(() => {
+    const query = athleteQuery.trim().toLowerCase();
+    if (!query) return athletes;
+    return athletes.filter((athlete) => athlete.full_name.toLowerCase().includes(query) || athlete.email?.toLowerCase().includes(query));
+  }, [athletes, athleteQuery]);
+  const filteredPlans = useMemo(() => {
+    if (!newPlan.assigned_user_id) return plans;
+    return plans.filter((plan) => Number(plan.assigned_user_id) === Number(newPlan.assigned_user_id));
+  }, [plans, newPlan.assigned_user_id]);
 
   async function load() {
     setLoading(true);
@@ -34,11 +48,10 @@ export function Workouts({ user, notify }) {
 
   async function createPlan(event) {
     event.preventDefault();
-    const athlete = users.find((item) => Number(item.id) === Number(newPlan.assigned_user_id));
     try {
       const data = await api.createPlan({
         assigned_user_id: canManage(user) ? newPlan.assigned_user_id : user.id,
-        name: athlete ? athlete.full_name : user.full_name
+        name: selectedAthlete ? selectedAthlete.full_name : user.full_name
       });
       notify('Programma creato');
       go(`/plan?id=${data.id}`);
@@ -47,6 +60,20 @@ export function Workouts({ user, notify }) {
     }
   }
 
+  function selectAthlete(athlete) {
+    setNewPlan({ assigned_user_id: athlete.id });
+    setAthleteQuery(athlete.full_name);
+    setAthleteMenuOpen(false);
+    setActiveAthleteIndex(0);
+  }
+
+  function resetAthleteFilter() {
+    setNewPlan({ assigned_user_id: '' });
+    setAthleteQuery('');
+    setAthleteMenuOpen(false);
+    setActiveAthleteIndex(0);
+  }
+
   async function deletePlan(event, plan) {
     event.stopPropagation();
     if (!window.confirm(`Eliminare definitivamente "${plan.name}"?`)) return;
@@ -62,20 +89,94 @@ export function Workouts({ user, notify }) {
   return (
     <section className="page">
       <div className="page-title row">
-        <div><h2>Lista allenamenti</h2></div>
+        <div>
+          <h2>Lista allenamenti</h2>
+          <p>Gestisci schede esistenti e crea nuovi programmi per gli atleti.</p>
+        </div>
+        <div className="users-summary" aria-live="polite">
+          {filteredPlans.length} schede
+        </div>
       </div>
       {canManage(user) && (
-        <form className="toolbar" onSubmit={createPlan}>
-          <select value={newPlan.assigned_user_id} onChange={(e) => setNewPlan({ ...newPlan, assigned_user_id: e.target.value })} required>
-            <option value="" disabled>Seleziona atleta</option>
-            {users.filter((u) => ['atleta', 'autonomo'].includes(u.role)).map((u) => <option key={u.id} value={u.id}>{u.full_name}</option>)}
-          </select>
-          <button className="primary"><Plus size={18} /> Nuovo</button>
+        <form className={['panel-form', 'workouts-toolbar', athleteMenuOpen ? 'is-open' : ''].filter(Boolean).join(' ')} onSubmit={createPlan}>
+          <div className="athlete-combobox">
+            <label htmlFor="workout-athlete-search">Seleziona atleta</label>
+            <div className="athlete-combobox-field">
+              <input
+                id="workout-athlete-search"
+                type="search"
+                role="combobox"
+                aria-expanded={athleteMenuOpen}
+                aria-controls="workout-athlete-options"
+                aria-activedescendant={athleteMenuOpen && filteredAthletes[activeAthleteIndex] ? `workout-athlete-option-${filteredAthletes[activeAthleteIndex].id}` : undefined}
+                aria-autocomplete="list"
+                autoComplete="off"
+                placeholder="Cerca atleta"
+                value={athleteQuery}
+                onFocus={() => setAthleteMenuOpen(true)}
+                onBlur={() => window.setTimeout(() => setAthleteMenuOpen(false), 120)}
+                onChange={(event) => {
+                  setAthleteQuery(event.target.value);
+                  setNewPlan({ assigned_user_id: '' });
+                  setAthleteMenuOpen(true);
+                  setActiveAthleteIndex(0);
+                }}
+                onKeyDown={(event) => {
+                  if (event.key === 'Escape') {
+                    setAthleteMenuOpen(false);
+                    return;
+                  }
+                  if (event.key === 'ArrowDown') {
+                    event.preventDefault();
+                    setAthleteMenuOpen(true);
+                    setActiveAthleteIndex((current) => Math.min(current + 1, Math.max(filteredAthletes.length - 1, 0)));
+                    return;
+                  }
+                  if (event.key === 'ArrowUp') {
+                    event.preventDefault();
+                    setActiveAthleteIndex((current) => Math.max(current - 1, 0));
+                    return;
+                  }
+                  if (event.key === 'Enter' && athleteMenuOpen && filteredAthletes[activeAthleteIndex]) {
+                    event.preventDefault();
+                    selectAthlete(filteredAthletes[activeAthleteIndex]);
+                  }
+                }}
+              />
+              {selectedAthlete && (
+                <button className="athlete-clear" type="button" onClick={resetAthleteFilter} aria-label="Mostra tutti gli atleti">
+                  Tutti
+                </button>
+              )}
+            </div>
+            {athleteMenuOpen && (
+              <div className="athlete-combobox-options" id="workout-athlete-options" role="listbox">
+                {filteredAthletes.map((athlete, index) => (
+                  <button
+                    id={`workout-athlete-option-${athlete.id}`}
+                    key={athlete.id}
+                    type="button"
+                    role="option"
+                    aria-selected={Number(athlete.id) === Number(newPlan.assigned_user_id)}
+                    className={index === activeAthleteIndex ? 'active' : ''}
+                    onMouseDown={(event) => event.preventDefault()}
+                    onClick={() => selectAthlete(athlete)}
+                    onMouseEnter={() => setActiveAthleteIndex(index)}
+                  >
+                    <strong>{athlete.full_name}</strong>
+                    {athlete.email && <span>{athlete.email}</span>}
+                  </button>
+                ))}
+                {filteredAthletes.length === 0 && <span className="athlete-combobox-empty">Nessun atleta trovato</span>}
+              </div>
+            )}
+          </div>
+          <button className="primary" disabled={canManage(user) && !newPlan.assigned_user_id}><Plus size={18} /> Nuovo</button>
         </form>
       )}
       {loading ? <Loading /> : (
         <div className="list workout-list">
-          {plans.map((plan) => (
+          {filteredPlans.map((plan) => (
             <div className="list-row workout-row" role="button" tabIndex="0" key={plan.id} onClick={() => go(`/plan?id=${plan.id}`)} onKeyDown={(event) => { if (event.key === 'Enter') go(`/plan?id=${plan.id}`); }}>
               <span className="workout-row-main">
                 <span className="workout-row-icon" aria-hidden="true"><CalendarDays size={18} /></span>
@@ -92,10 +193,10 @@ export function Workouts({ user, notify }) {
               )}
             </div>
           ))}
-          {plans.length === 0 && (
+          {filteredPlans.length === 0 && (
             <div className="empty empty-card">
               <strong>Nessun allenamento ancora</strong>
-              <span>{canSelfManageWorkouts(user) ? 'Apri Crea scheda per iniziare.' : canManage(user) ? 'Crea una nuova scheda per iniziare.' : 'Il tuo coach non ha ancora assegnato una scheda.'}</span>
+              <span>{canSelfManageWorkouts(user) ? 'Apri Crea scheda per iniziare.' : canManage(user) && selectedAthlete ? 'Nessuna scheda collegata a questo atleta.' : canManage(user) ? 'Crea una nuova scheda per iniziare.' : 'Il tuo coach non ha ancora assegnato una scheda.'}</span>
             </div>
           )}
         </div>
diff --git a/frontend/src/styles.css b/frontend/src/styles.css
index f9f697f..1ea2a98 100644
--- a/frontend/src/styles.css
+++ b/frontend/src/styles.css
@@ -19,6 +19,13 @@
   --shadow-soft: 0 14px 38px rgba(24, 17, 20, 0.07);
   --shadow-lift: 0 18px 46px rgba(24, 17, 20, 0.1);
   --shadow: var(--shadow-sm);
+  --card-radius: 16px;
+  --card-radius-sm: 12px;
+  --card-padding: 18px;
+  --control-gap: 14px;
+  --panel-bg: rgba(255, 255, 255, 0.94);
+  --panel-border: rgba(225, 216, 221, 0.95);
+  --panel-shadow: 0 14px 36px rgba(24, 17, 20, 0.05);
   --font-sans: "Plus Jakarta Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
   --text-sm: 0.875rem;
   --text-lg: 1.125rem;
@@ -29,6 +36,9 @@
   --radius-xl: 20px;
   --leading-tight: 1.2;
   --leading-normal: 1.5;
+  --motion-fast: 120ms;
+  --motion-medium: 160ms;
+  --motion-slow: 220ms;
   font-family: var(--font-sans);
   color: var(--ink);
   background: var(--bg);
@@ -255,7 +265,7 @@ button:disabled {
   line-height: var(--leading-normal);
 }
 
-.login-footer span + span::before {
+.login-footer span+span::before {
   content: "";
   display: inline-block;
   width: 4px;
@@ -367,11 +377,10 @@ input:disabled {
   flex-direction: column;
   gap: 20px;
   padding: 22px 18px;
-  background:
-    radial-gradient(circle at 28% 0%, rgba(146, 20, 38, 0.24), transparent 16rem),
-    linear-gradient(180deg, #160b0f 0%, #2f1118 54%, #111014 100%);
+  background: linear-gradient(180deg, #13161b 0%, #111214 100%);
+  border-right: 1px solid rgba(255, 255, 255, 0.08);
   color: white;
-  box-shadow: 6px 0 28px rgba(18, 7, 10, 0.12);
+  box-shadow: 6px 0 26px rgba(0, 0, 0, 0.2);
 }
 
 .sidebar-head {
@@ -404,11 +413,12 @@ input:disabled {
 .icon-button {
   min-height: 44px;
   border: 0;
-  border-radius: var(--radius-md);
+  border-radius: var(--card-radius-sm);
   display: inline-flex;
   align-items: center;
   justify-content: center;
-  gap: 8px;
+  gap: 10px;
+  padding: 0 16px;
 }
 
 .sidebar nav button,
@@ -452,24 +462,45 @@ input:disabled {
   margin-top: 0;
 }
 
+.sidebar-meta {
+  display: grid;
+  gap: 4px;
+  padding-bottom: 12px;
+  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
+  color: rgba(255, 255, 255, 0.76);
+}
+
+.sidebar-meta strong {
+  color: #fff;
+  font-size: 0.99rem;
+}
+
+.sidebar-meta span {
+  font-size: 0.81rem;
+  text-transform: capitalize;
+}
+
+.sidebar-actions {
+  display: grid;
+  gap: 10px;
+  margin-top: auto;
+}
+
 .main-area {
   min-width: 0;
-  background:
-    radial-gradient(circle at 92% 4%, rgba(15, 118, 110, 0.075), transparent 24rem),
-    radial-gradient(circle at 18% 0%, rgba(146, 20, 38, 0.055), transparent 26rem),
-    linear-gradient(180deg, #fbf8f9 0%, #f4f0f2 46%, #eef5f4 100%);
+  background: #f5f6f7;
 }
 
 .topbar {
   min-height: 72px;
   display: flex;
   align-items: center;
-  justify-content: flex-end;
+  justify-content: space-between;
   gap: 12px;
   padding: 0 28px;
   border-bottom: 1px solid rgba(224, 211, 216, 0.82);
-  background: rgba(255, 255, 255, 0.82);
-  backdrop-filter: none;
+  background: rgba(255, 255, 255, 0.95);
+  backdrop-filter: blur(12px);
 }
 
 .topbar div {
@@ -477,6 +508,29 @@ input:disabled {
   text-align: right;
 }
 
+.topbar-left {
+  display: flex;
+  align-items: center;
+  gap: 12px;
+}
+
+.topbar-page-label {
+  color: var(--muted);
+  font-size: 0.95rem;
+  font-weight: 600;
+}
+
+.topbar-user {
+  display: grid;
+  gap: 2px;
+  text-align: right;
+}
+
+.topbar-user span {
+  color: var(--muted);
+  text-transform: capitalize;
+}
+
 .topbar strong {
   font-weight: 600;
   color: var(--ink);
@@ -490,9 +544,44 @@ input:disabled {
 }
 
 .page {
-  width: min(1180px, 100%);
+  width: 100%;
+  max-width: 1280px;
   margin: 0 auto;
-  padding: 34px max(28px, env(safe-area-inset-right)) 40px max(28px, env(safe-area-inset-left));
+  padding: 32px max(28px, env(safe-area-inset-right)) 40px max(28px, env(safe-area-inset-left));
+}
+
+.users-summary {
+  color: var(--muted);
+  font-size: var(--text-sm);
+  font-weight: 600;
+  text-align: right;
+}
+
+.user-detail-actions {
+  display: flex;
+  align-items: center;
+  justify-content: space-between;
+  gap: 14px;
+  margin-bottom: 20px;
+  flex-wrap: wrap;
+}
+
+.user-detail-badges {
+  display: inline-flex;
+  gap: 10px;
+  flex-wrap: wrap;
+}
+
+.user-detail-buttons {
+  display: flex;
+  gap: 10px;
+  flex-wrap: wrap;
+  justify-content: flex-end;
+}
+
+.panel-form input,
+.panel-form select {
+  min-height: 48px;
 }
 
 .page-title {
@@ -515,6 +604,11 @@ input:disabled {
   line-height: var(--leading-normal);
 }
 
+.plan-editor-flow {
+  width: min(980px, 100%);
+  margin: 0 auto;
+}
+
 .profile-title-actions {
   display: flex;
   align-items: center;
@@ -606,13 +700,11 @@ input:disabled {
   align-items: center;
   gap: 16px;
   margin-bottom: 18px;
-  padding: 17px;
-  border: 1px solid rgba(224, 211, 216, 0.9);
-  border-radius: var(--radius-xl);
-  background:
-    radial-gradient(circle at 100% 0%, rgba(146, 20, 38, 0.052), transparent 18rem),
-    linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #fffafa 100%);
-  box-shadow: var(--shadow-soft);
+  padding: var(--card-padding);
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--panel-shadow);
 }
 
 .user-info-main {
@@ -628,7 +720,7 @@ input:disabled {
   border-radius: 16px;
 }
 
-.user-info-main > div {
+.user-info-main>div {
   min-width: 0;
   display: grid;
   gap: 3px;
@@ -645,7 +737,7 @@ input:disabled {
   gap: 9px;
 }
 
-.user-info-grid > div {
+.user-info-grid>div {
   min-height: 66px;
   display: grid;
   align-content: center;
@@ -693,11 +785,10 @@ input:disabled {
 .day,
 .panel-form,
 .editor-bottom-actions {
-  border: 1px solid #ecd4d9;
-  border-radius: 20px;
-  background:
-    linear-gradient(180deg, #ffffff 0%, var(--surface-warm) 100%);
-  box-shadow: 0 10px 26px rgba(24, 17, 20, 0.05);
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--shadow-sm);
   backdrop-filter: none;
 }
 
@@ -721,6 +812,18 @@ input:disabled {
   color: var(--brand);
 }
 
+/* Unified subtle motion for card-like elements */
+.list-row,
+.user-card,
+.workout-row,
+.metric,
+.panel-form,
+.athlete-exercise,
+.exercise,
+.parser-panel {
+  transition: transform var(--motion-medium) ease, box-shadow var(--motion-medium) ease, background var(--motion-medium) ease, border-color var(--motion-medium) ease;
+}
+
 .metric strong {
   font-size: var(--text-xl);
   font-weight: 600;
@@ -742,6 +845,126 @@ input:disabled {
   justify-content: start;
 }
 
+.workouts-toolbar {
+  display: flex;
+  align-items: flex-end;
+  gap: 12px;
+  margin-bottom: 22px;
+  transition: padding-bottom 160ms ease;
+}
+
+.workouts-toolbar.is-open {
+  padding-bottom: 232px;
+}
+
+.workouts-toolbar .primary {
+  align-self: end;
+  min-height: 44px;
+  padding: 0 12px;
+  box-shadow: 0 6px 14px rgba(146, 20, 38, 0.12);
+}
+
+.athlete-combobox {
+  position: relative;
+  display: grid;
+  gap: 7px;
+  width: min(320px, 100%);
+  min-width: 0;
+}
+
+.athlete-combobox label {
+  color: var(--muted);
+  font-size: var(--text-xs);
+  font-weight: 700;
+  text-transform: uppercase;
+  letter-spacing: 0.04em;
+}
+
+.athlete-combobox-field {
+  position: relative;
+  display: flex;
+  align-items: center;
+}
+
+.athlete-combobox input {
+  width: 100%;
+  min-height: 46px;
+  padding-right: 64px;
+  border-color: var(--panel-border);
+  border-radius: var(--card-radius-sm);
+  background: var(--surface);
+}
+
+.athlete-clear {
+  position: absolute;
+  right: 7px;
+  min-height: 30px;
+  padding: 0 9px;
+  border: 1px solid var(--panel-border);
+  border-radius: 999px;
+  background: #fff;
+  color: var(--brand-dark);
+  font-size: var(--text-xs);
+  font-weight: 700;
+  cursor: pointer;
+}
+
+.athlete-combobox-options {
+  position: absolute;
+  z-index: 30;
+  top: calc(100% + 6px);
+  left: 0;
+  right: 0;
+  display: grid;
+  gap: 6px;
+  max-height: 220px;
+  overflow-y: auto;
+  padding: 10px;
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--shadow-soft);
+}
+
+.athlete-combobox-options button {
+  display: grid;
+  gap: 2px;
+  width: 100%;
+  min-height: 50px;
+  padding: 9px 10px;
+  border: 1px solid transparent;
+  border-radius: 12px;
+  background: transparent;
+  color: var(--ink);
+  text-align: left;
+  cursor: pointer;
+  transition: border-color 150ms ease, background 150ms ease, color 150ms ease;
+}
+
+.athlete-combobox-options button:hover,
+.athlete-combobox-options button:focus-visible,
+.athlete-combobox-options button.active,
+.athlete-combobox-options button[aria-selected="true"] {
+  border-color: rgba(146, 20, 38, 0.18);
+  background: var(--brand-soft);
+  color: var(--brand-dark);
+}
+
+.athlete-combobox-options button strong {
+  font-size: var(--text-sm);
+  font-weight: 700;
+}
+
+.athlete-combobox-options button span,
+.athlete-combobox-empty {
+  color: var(--muted);
+  font-size: var(--text-xs);
+}
+
+.athlete-combobox-empty {
+  padding: 12px;
+}
+
 .search-toolbar {
   grid-template-columns: minmax(220px, 520px) max-content;
 }
@@ -762,12 +985,11 @@ input:disabled {
   width: fit-content;
   display: inline-flex;
   align-items: center;
-  gap: 5px;
-  padding: 5px;
-  border: 1px solid rgba(224, 211, 216, 0.86);
+  gap: 6px;
+  padding: 6px;
+  border: 1px solid var(--panel-border);
   border-radius: 999px;
-  background: rgba(255, 255, 255, 0.72);
-  box-shadow: 0 8px 22px rgba(24, 17, 20, 0.045);
+  background: rgba(255, 255, 255, 0.82);
 }
 
 .users-status-filter button {
@@ -817,8 +1039,24 @@ input:disabled {
   width: 100%;
 }
 
+.users-search-field {
+  position: relative;
+  display: flex;
+  align-items: center;
+}
+
+.users-search-field svg {
+  position: absolute;
+  left: 12px;
+  color: var(--muted);
+  pointer-events: none;
+}
+
 .users-search input {
+  width: 100%;
   min-height: 44px;
+  padding-left: 38px;
+  padding-right: 38px;
   border-color: rgba(224, 211, 216, 0.92);
   border-radius: 12px;
   background: rgba(255, 255, 255, 0.9);
@@ -833,27 +1071,44 @@ input:disabled {
   border-radius: 12px;
 }
 
+.users-search-clear {
+  position: absolute;
+  right: 7px;
+  width: 28px;
+  min-height: 28px !important;
+  padding: 0;
+  display: grid;
+  place-items: center;
+  border: 0;
+  border-radius: 999px;
+  background: transparent;
+  color: var(--muted);
+  cursor: pointer;
+}
+
+.users-search-clear:hover,
+.users-search-clear:focus-visible {
+  background: var(--brand-soft);
+  color: var(--brand-dark);
+}
+
 .users-list {
   gap: 9px;
 }
 
 .user-card {
-  min-height: 74px;
-  padding: 13px 15px;
-  border-color: rgba(224, 211, 216, 0.9);
-  border-radius: 16px;
-  background:
-    radial-gradient(circle at 100% 0%, rgba(15, 118, 110, 0.04), transparent 13rem),
-    linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #fffafb 100%);
-  box-shadow: 0 8px 22px rgba(24, 17, 20, 0.045);
+  min-height: 72px;
+  padding: 14px 16px;
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--shadow-sm);
 }
 
 .user-card:not(.static):hover {
   border-color: rgba(146, 20, 38, 0.17);
-  background:
-    radial-gradient(circle at 100% 0%, rgba(15, 118, 110, 0.06), transparent 13rem),
-    linear-gradient(180deg, #ffffff 0%, #fff8f9 100%);
-  box-shadow: 0 12px 28px rgba(24, 17, 20, 0.07);
+  background: var(--panel-bg);
+  box-shadow: 0 12px 26px rgba(24, 17, 20, 0.08);
   transform: translateY(-1px);
 }
 
@@ -864,7 +1119,7 @@ input:disabled {
   min-width: 0;
 }
 
-.user-card-main > span:last-child {
+.user-card-main>span:last-child {
   min-width: 0;
   display: grid;
   gap: 2px;
@@ -943,10 +1198,14 @@ input:disabled {
 
 .panel-form {
   display: grid;
-  grid-template-columns: repeat(2, minmax(180px, 1fr));
-  gap: 10px;
-  padding: 18px;
+  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
+  gap: var(--control-gap);
+  padding: var(--card-padding);
   margin-bottom: 24px;
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--panel-shadow);
 }
 
 .programming-panel {
@@ -996,14 +1255,12 @@ input:disabled {
 .ticket-panel {
   width: min(760px, 100%);
   display: grid;
-  gap: 15px;
-  padding: 20px;
-  border: 1px solid rgba(224, 211, 216, 0.88);
-  border-radius: var(--radius-xl);
-  background:
-    radial-gradient(circle at 100% 0%, rgba(146, 20, 38, 0.055), transparent 22rem),
-    linear-gradient(180deg, rgba(255, 255, 255, 0.97) 0%, #fffafa 100%);
-  box-shadow: var(--shadow-soft);
+  gap: 16px;
+  padding: var(--card-padding);
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
+  box-shadow: var(--panel-shadow);
 }
 
 .ticket-field {
@@ -1011,20 +1268,20 @@ input:disabled {
   gap: 8px;
 }
 
-.ticket-field > span {
+.ticket-field>span {
   color: var(--ink);
   font-weight: 700;
 }
 
 .ticket-field textarea {
-  min-height: 176px;
+  min-height: 160px;
   padding: 14px;
-  border-color: rgba(224, 211, 216, 0.92);
-  border-radius: 15px;
-  background: rgba(250, 248, 249, 0.78);
-  line-height: 1.55;
+  border-color: var(--panel-border);
+  border-radius: var(--card-radius-sm);
+  background: #fbf9fa;
+  line-height: 1.5;
   resize: vertical;
-  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.74);
+  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
 }
 
 .ticket-field textarea::placeholder {
@@ -1054,10 +1311,11 @@ input:disabled {
 .ticket-upload-button {
   position: relative;
   overflow: hidden;
-  min-height: 40px;
-  padding-inline: 13px;
-  border-color: rgba(224, 211, 216, 0.92);
-  background: rgba(255, 255, 255, 0.72);
+  min-height: 42px;
+  padding: 0 16px;
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius-sm);
+  background: var(--surface);
   color: #514a50;
   font-weight: 600;
 }
@@ -1088,10 +1346,10 @@ input:disabled {
   align-items: center;
   gap: 8px;
   max-width: 100%;
-  padding: 0 8px 0 12px;
-  border: 1px solid rgba(224, 211, 216, 0.9);
+  padding: 0 12px;
+  border: 1px solid var(--panel-border);
   border-radius: 999px;
-  background: rgba(255, 255, 255, 0.82);
+  background: #fff;
 }
 
 .ticket-file span {
@@ -1123,13 +1381,15 @@ input:disabled {
 }
 
 .primary {
-  padding: 0 18px;
+  padding: 0 14px;
   background:
     linear-gradient(180deg, var(--brand-strong), var(--brand));
   color: white;
   font-weight: 600;
-  box-shadow: 0 12px 24px rgba(146, 20, 38, 0.18);
-  transition: background 160ms ease, box-shadow 160ms ease, transform 150ms ease;
+  box-shadow: 0 6px 14px rgba(146, 20, 38, 0.12);
+  min-height: 44px;
+  line-height: 1;
+  transition: background var(--motion-medium) ease, box-shadow var(--motion-medium) ease, transform var(--motion-fast) ease;
 }
 
 .danger-solid {
@@ -1144,10 +1404,17 @@ input:disabled {
 .primary:hover {
   background:
     linear-gradient(180deg, #c11f39, var(--brand));
-  box-shadow: 0 14px 30px rgba(146, 20, 38, 0.24);
+  box-shadow: 0 10px 22px rgba(146, 20, 38, 0.14);
   transform: translateY(-1px);
 }
 
+button:focus-visible,
+.primary:focus-visible {
+  outline: none;
+  box-shadow: 0 0 0 4px var(--brand-ring);
+  border-color: var(--brand);
+}
+
 .primary:active {
   box-shadow: 0 8px 18px rgba(146, 20, 38, 0.2);
   transform: translateY(0);
@@ -1310,7 +1577,7 @@ button:disabled {
 
 .list-row {
   width: 100%;
-  min-height: 76px;
+  min-height: 70px;
   padding: 16px 18px;
   display: flex;
   align-items: center;
@@ -1319,25 +1586,25 @@ button:disabled {
   text-align: left;
   color: var(--ink);
   cursor: pointer;
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
   transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
+  box-shadow: var(--shadow-sm);
 }
 
 .workout-row {
-  min-height: 92px;
+  min-height: 88px;
   padding: 18px 20px;
-  border-radius: var(--radius-xl);
-  border-color: rgba(146, 20, 38, 0.12);
-  background:
-    radial-gradient(circle at 96% 0%, rgba(15, 118, 110, 0.055), transparent 12rem),
-    linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #fffafa 100%);
-  box-shadow: var(--shadow-soft);
+  border-radius: var(--card-radius);
+  border: 1px solid var(--panel-border);
+  background: var(--panel-bg);
+  box-shadow: var(--shadow-sm);
 }
 
 .workout-row:not(.static):hover {
   border-color: rgba(146, 20, 38, 0.18);
-  background:
-    radial-gradient(circle at 96% 0%, rgba(15, 118, 110, 0.075), transparent 12rem),
-    linear-gradient(180deg, #ffffff 0%, #fff8f9 100%);
+  background: var(--panel-bg);
   box-shadow: var(--shadow-lift);
   transform: translateY(-1px);
 }
@@ -1348,7 +1615,7 @@ button:disabled {
   gap: 16px !important;
 }
 
-.workout-row-main > span:last-child {
+.workout-row-main>span:last-child {
   display: grid;
   gap: 4px;
   align-content: center;
@@ -1393,12 +1660,12 @@ button:disabled {
   cursor: default;
 }
 
-.list-row > span:not(:first-child):not(.badge-stack) {
+.list-row>span:not(:first-child):not(.badge-stack) {
   font-size: var(--text-sm);
   color: var(--muted);
 }
 
-.list-row > span:first-child {
+.list-row>span:first-child {
   display: grid;
   gap: 2px;
   flex: 1;
@@ -1529,11 +1796,9 @@ button:disabled {
 .empty-card {
   display: grid;
   gap: 5px;
-  border-style: solid;
-  border-color: #ecd4d9;
-  border-radius: 18px;
-  background:
-    linear-gradient(180deg, #ffffff 0%, #fff9fa 100%);
+  border: 1px solid var(--panel-border);
+  border-radius: var(--card-radius);
+  background: var(--panel-bg);
 }
 
 .empty-card strong {
@@ -1555,7 +1820,7 @@ button:disabled {
   line-height: var(--leading-normal);
 }
 
-.loading > span {
+.loading>span {
   font-weight: 600;
 }
 
@@ -1679,6 +1944,7 @@ button:disabled {
   justify-content: space-between;
   align-items: center;
   gap: 10px;
+  width: 100%;
   margin-top: 12px;
   padding: 10px 12px;
   border: 1px dashed rgba(224, 211, 216, 0.9);
@@ -1763,10 +2029,10 @@ button:disabled {
 
 .parser-panel {
   display: grid;
-  gap: 16px;
+  gap: 12px;
   width: min(980px, 100%);
-  margin: 0 auto 20px;
-  padding: 22px;
+  margin: 0 auto 16px;
+  padding: 18px;
   border: 1px solid rgba(224, 211, 216, 0.88);
   border-radius: var(--radius-xl);
   background:
@@ -1817,8 +2083,8 @@ button:disabled {
 }
 
 .parser-textarea {
-  min-height: 190px;
-  padding: 16px;
+  min-height: 165px;
+  padding: 14px;
   line-height: 1.58;
   resize: vertical;
   border-color: rgba(224, 211, 216, 0.92);
@@ -1908,7 +2174,7 @@ button:disabled {
   grid-template-columns: auto minmax(0, 1fr);
   gap: 12px;
   align-items: center;
-  padding: 16px;
+  padding: 14px;
   border: 1px dashed rgba(146, 20, 38, 0.2);
   border-radius: var(--radius-lg);
   background:
@@ -1936,6 +2202,23 @@ button:disabled {
   font-size: var(--text-sm);
 }
 
+.parser-empty-examples {
+  display: flex !important;
+  flex-wrap: wrap;
+  gap: 6px !important;
+  padding-top: 4px;
+}
+
+.parser-empty-examples code {
+  padding: 4px 8px;
+  border: 1px solid rgba(224, 211, 216, 0.82);
+  border-radius: 999px;
+  background: rgba(255, 255, 255, 0.7);
+  color: #514a50;
+  font-family: var(--font-sans);
+  font-size: 0.75rem;
+}
+
 .parser-day {
   display: grid;
   gap: 10px;
@@ -1947,7 +2230,7 @@ button:disabled {
   box-shadow: 0 8px 22px rgba(24, 17, 20, 0.045);
 }
 
-.parser-day > div:first-child {
+.parser-day>div:first-child {
   display: grid;
   gap: 3px;
 }
@@ -2001,7 +2284,7 @@ button:disabled {
   padding-top: 4px;
 }
 
-.parser-note-table > div {
+.parser-note-table>div {
   display: grid;
   grid-template-columns: repeat(3, minmax(90px, 1fr));
   gap: 8px;
@@ -2017,7 +2300,7 @@ button:disabled {
 
 .days {
   display: grid;
-  gap: 14px;
+  gap: 10px;
   width: min(980px, 100%);
   margin-inline: auto;
 }
@@ -2025,7 +2308,7 @@ button:disabled {
 .current-plan-head {
   display: flex;
   justify-content: space-between;
-  gap: 18px;
+  gap: 14px;
   align-items: flex-start;
   padding-bottom: 2px;
 }
@@ -2037,12 +2320,12 @@ button:disabled {
     linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #fff8f9 100%);
 }
 
-.current-plan-head > div {
+.current-plan-head>div {
   display: grid;
   gap: 3px;
 }
 
-.current-plan-head > div:first-child > span {
+.current-plan-head>div:first-child>span {
   color: var(--brand-dark);
   font-size: 0.71875rem;
   font-weight: 750;
@@ -2079,12 +2362,27 @@ button:disabled {
 }
 
 .current-plan-toolbar .editor-bottom-copy strong {
-  color: #514a50;
-  font-size: 0.8125rem;
-  font-weight: 600;
+  justify-self: end;
+  width: fit-content;
+  min-height: 24px;
+  display: inline-flex;
+  align-items: center;
+  padding: 0 9px;
+  border-radius: 999px;
+  font-size: 0.75rem;
+  font-weight: 700;
 }
 
+.current-plan-toolbar .editor-bottom-copy .status-saved {
+  border: 1px solid rgba(22, 101, 52, 0.16);
+  background: #ecfdf5;
+  color: #166534;
+}
+
+.current-plan-toolbar .editor-bottom-copy .status-unsaved,
 .day.is-editing .current-plan-toolbar .editor-bottom-copy strong {
+  border: 1px solid rgba(146, 20, 38, 0.18);
+  background: rgba(255, 241, 243, 0.78);
   color: var(--brand-dark);
 }
 
@@ -2098,8 +2396,8 @@ button:disabled {
 
 .current-plan-toolbar .ghost,
 .current-plan-toolbar .primary {
-  min-height: 36px;
-  padding-inline: 12px;
+  min-height: 34px;
+  padding-inline: 11px;
   flex: 0 0 auto;
 }
 
@@ -2149,20 +2447,21 @@ button:disabled {
 
 .day-tabs {
   display: flex;
-  gap: 10px;
-  margin-bottom: 16px;
+  gap: 8px;
+  width: min(980px, 100%);
+  margin: 0 auto 12px;
   overflow-x: auto;
-  padding: 4px 0 10px;
+  padding: 3px 0 8px;
   scrollbar-width: thin;
 }
 
 .day-tabs button {
   flex: 0 0 auto;
-  min-height: 44px;
-  display: inline-flex;
-  align-items: center;
-  gap: 6px;
-  padding: 0 18px;
+  min-height: 48px;
+  display: grid;
+  align-content: center;
+  gap: 2px;
+  padding: 0 16px;
   border: 1px solid rgba(224, 211, 216, 0.92);
   border-radius: 999px;
   background: rgba(255, 255, 255, 0.74);
@@ -2172,6 +2471,18 @@ button:disabled {
   transition: border-color 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease;
 }
 
+.day-tabs button span {
+  font-size: 0.875rem;
+  line-height: 1.1;
+}
+
+.day-tabs button small {
+  color: rgba(81, 74, 80, 0.72);
+  font-size: 0.6875rem;
+  font-weight: 650;
+  line-height: 1.1;
+}
+
 .day-tabs button.active {
   background:
     linear-gradient(180deg, var(--brand-strong), var(--brand));
@@ -2180,6 +2491,10 @@ button:disabled {
   box-shadow: 0 10px 22px rgba(146, 20, 38, 0.18);
 }
 
+.day-tabs button.active small {
+  color: rgba(255, 255, 255, 0.78);
+}
+
 .day-tabs button.completed {
   background: #ecfdf3;
   border-color: #bbf7d0;
@@ -2262,9 +2577,9 @@ button:disabled {
 }
 
 .day {
-  padding: 20px;
+  padding: 16px;
   display: grid;
-  gap: 16px;
+  gap: 12px;
   border-color: rgba(146, 20, 38, 0.12);
   border-radius: var(--radius-xl);
   background:
@@ -2274,7 +2589,7 @@ button:disabled {
 }
 
 .print-scope .day {
-  padding-inline: 22px;
+  padding-inline: 18px;
 }
 
 .day-head {
@@ -2283,7 +2598,7 @@ button:disabled {
   gap: 10px;
 }
 
-.day-head > input {
+.day-head>input {
   font-weight: 600;
   border-color: var(--line);
   background: var(--surface);
@@ -2292,7 +2607,7 @@ button:disabled {
 
 .exercise-list {
   display: grid;
-  gap: 14px;
+  gap: 10px;
 }
 
 .exercise {
@@ -2310,7 +2625,7 @@ button:disabled {
   min-width: 0;
 }
 
-.exercise > * {
+.exercise>* {
   min-width: 0;
 }
 
@@ -2350,9 +2665,9 @@ button:disabled {
 .athlete-exercise {
   display: grid;
   grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
-  gap: 16px;
+  gap: 12px;
   align-items: stretch;
-  padding: 17px 18px;
+  padding: 10px 12px;
   border: 1px solid rgba(224, 211, 216, 0.9);
   border-left: 3px solid rgba(146, 20, 38, 0.72);
   border-radius: var(--radius-lg);
@@ -2441,7 +2756,7 @@ button:disabled {
 
 .athlete-exercise-main {
   display: grid;
-  gap: 12px;
+  gap: 7px;
   align-content: start;
 }
 
@@ -2451,7 +2766,7 @@ button:disabled {
 
 .athlete-exercise-main strong {
   color: var(--ink);
-  font-size: var(--text-lg);
+  font-size: 1rem;
   font-weight: 700;
   line-height: var(--leading-tight);
 }
@@ -2459,16 +2774,16 @@ button:disabled {
 .exercise-values {
   display: flex;
   flex-wrap: wrap;
-  gap: 8px;
+  gap: 6px;
 }
 
 .exercise-values span {
   display: grid;
   min-width: 88px;
-  min-height: 54px;
+  min-height: 48px;
   gap: 2px;
   align-content: center;
-  padding: 8px 11px;
+  padding: 6px 9px;
   border: 1px solid rgba(224, 211, 216, 0.88);
   border-radius: 13px;
   background: rgba(255, 247, 248, 0.72);
@@ -2477,6 +2792,24 @@ button:disabled {
   box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
 }
 
+.exercise-values.compact {
+  gap: 7px;
+}
+
+.exercise-values.compact .exercise-inline-meta {
+  min-width: 0;
+  min-height: 0;
+  display: inline-flex;
+  align-items: center;
+  padding: 0;
+  border: 0;
+  background: transparent;
+  box-shadow: none;
+  color: #5f575e;
+  font-size: 0.875rem;
+  font-weight: 600;
+}
+
 .exercise-values small {
   color: #756d73;
   font-size: 0.6875rem;
@@ -2495,6 +2828,16 @@ button:disabled {
   white-space: pre-line;
 }
 
+.athlete-exercise .exercise-actions .ghost {
+  min-height: 32px;
+  padding-inline: 9px;
+  border-color: rgba(224, 211, 216, 0.74);
+  background: rgba(255, 255, 255, 0.58);
+  color: #625960;
+  font-size: 0.8125rem;
+  font-weight: 550;
+}
+
 .exercise-structured-notes {
   display: grid;
   gap: 10px;
@@ -2506,7 +2849,7 @@ button:disabled {
   gap: 10px;
 }
 
-.exercise-structured-row > div {
+.exercise-structured-row>div {
   display: grid;
   gap: 4px;
   min-height: 54px;
@@ -2533,8 +2876,9 @@ button:disabled {
 }
 
 .athlete-exercise textarea {
-  min-height: 78px;
-  padding: 11px 12px;
+  min-height: 66px;
+  min-height: 56px;
+  padding: 8px 10px;
   resize: vertical;
   border-color: rgba(224, 211, 216, 0.78);
   border-radius: 13px;
@@ -2586,6 +2930,12 @@ button:disabled {
   color: var(--brand);
 }
 
+.confirm-icon.info {
+  background: rgba(13, 96, 88, 0.08);
+  color: #0d6058;
+  border: 1px solid rgba(13, 96, 88, 0.16);
+}
+
 .confirm-icon.danger {
   background: #fef2f2;
   color: #dc2626;
@@ -2606,12 +2956,33 @@ button:disabled {
   color: var(--muted);
   font-size: var(--text-sm);
   line-height: var(--leading-normal);
+  white-space: pre-line;
 }
 
-.confirm-actions {
-  grid-column: 1 / -1;
+.confirm-preference {
   display: flex;
-  justify-content: flex-end;
+  align-items: center;
+  gap: 8px;
+  width: fit-content;
+  margin-top: 4px;
+  color: var(--ink);
+  font-size: var(--text-sm);
+  font-weight: 550;
+  cursor: pointer;
+}
+
+.confirm-preference input {
+  width: 16px;
+  height: 16px;
+.athlete-exercise .exercise-actions .ghost {
+  min-height: 30px;
+  padding-inline: 8px;
+  border-color: rgba(224, 211, 216, 0.74);
+  background: rgba(255, 255, 255, 0.45);
+  color: #625960;
+  font-size: 0.8125rem;
+  font-weight: 500;
+}
   gap: 10px;
   padding-top: 4px;
 }
@@ -2754,7 +3125,50 @@ button:disabled {
   }
 
   .page {
-    padding: 18px max(12px, env(safe-area-inset-right)) max(54px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
+    padding: 22px 16px 32px 16px;
+    width: 100%;
+  }
+
+  .users-controls {
+    grid-template-columns: 1fr;
+    align-items: stretch;
+  }
+
+  .users-search {
+    justify-self: stretch;
+  }
+
+  .user-info-card {
+    grid-template-columns: 1fr;
+  }
+
+  .panel-form {
+    grid-template-columns: 1fr;
+  }
+
+  .user-detail-actions {
+    flex-direction: column;
+    align-items: flex-start;
+  }
+
+  .topbar-left {
+    gap: 10px;
+  }
+
+  .sidebar-meta {
+    padding-bottom: 10px;
+  }
+
+  .sidebar-actions {
+    margin-top: 18px;
+  }
+
+  .page-title.row {
+    align-items: flex-start;
+  }
+
+  .users-summary {
+    text-align: left;
   }
 
   .login-page {
@@ -2948,7 +3362,7 @@ button:disabled {
     gap: 8px;
   }
 
-  .day-head > input {
+  .day-head>input {
     min-height: 42px;
     padding-inline: 12px;
     border-radius: 12px;
@@ -3037,7 +3451,7 @@ button:disabled {
     gap: 8px;
   }
 
-  .user-info-grid > div {
+  .user-info-grid>div {
     min-height: 58px;
     padding: 9px 10px;
   }
@@ -3069,6 +3483,26 @@ button:disabled {
     justify-content: center;
   }
 
+  .workouts-toolbar .primary {
+    width: 100%;
+    justify-content: center;
+    padding: 0 12px;
+    box-shadow: 0 6px 14px rgba(146, 20, 38, 0.12);
+  }
+
+  .workouts-toolbar {
+    display: grid;
+    grid-template-columns: 1fr;
+  }
+
+  .workouts-toolbar.is-open {
+    padding-bottom: 232px;
+  }
+
+  .athlete-combobox {
+    width: 100%;
+  }
+
   .ticket-title {
     align-items: flex-start;
     gap: 10px;
@@ -3340,7 +3774,7 @@ button:disabled {
     justify-content: center;
   }
 
-  .app-shell > .main-area .sticky-actions {
+  .app-shell>.main-area .sticky-actions {
     top: 68px;
     overflow-x: auto;
     justify-content: flex-start;
```

## Follow-up senior review fixes

- Fixed the broken CSS block in `frontend/src/styles.css` by separating `.confirm-preference input`, `.confirm-actions`, and `.athlete-exercise .exercise-actions .ghost`.
- Restored the desktop base layout for `.confirm-actions`.
- Kept `Shell.jsx` free of the hardcoded "Pannello di controllo" label and left no active `.topbar-page-label` usage in source styles.
- Confirmed `UserDetail.jsx` only keeps the "Crea scheda" action inside the `Programmazione` panel.
- Split the Workouts athlete filter state from the new-plan creation state and moved the creation submit to the `Nuovo` button form only.
- Prevented Enter in the athlete search input from accidentally creating a workout.
- Added `frontend/test-results/` to `.gitignore`.
- Updated Playwright smoke paths to hash routing and removed the temporary `test:smoke` script because the tests do not authenticate/mock a real session yet.
- Removed the temporary Playwright config/tests/dependency from the active diff until a realistic authenticated smoke setup is added.
- Removed misleading `Ultimo salvataggio` copy and removed per-tab dirty status that was global rather than per-day.

## PlanEditor action simplification

- Removed the `Duplica` exercise action and its dedicated handler from `PlanEditor.jsx`.
- Removed `Modifica nel parser` from exercise cards while manual editing is active; edit mode now shows only `Rimuovi` per exercise/block.
- Kept `Modifica nel parser` available in the non-editing current-plan view.
- Tightened the mobile exercise action grid so a single `Rimuovi` button no longer reserves a three-column action row.

## PlanEditor toolbar cleanup

- Hid `Copia nel parser` from the current-plan toolbar while manual editing is active.
- Manual edit toolbar now exposes only `Annulla` and `Salva`.
- Added mobile-only sizing for the athlete assignment select so it stays full-width, touch-friendly, and aligned with the PlanEditor flow.
- Reduced mobile editing toolbar spacing slightly without changing desktop behavior.

## Workout parser natural circuits

- Extended the backend workout parser to recognize natural-language circuit headers such as `Circuito x 4 con`, `Circuito 4 giri`, `circuito per 4 giri`, and `4 giri di`.
- Added structured circuit parsing for newline/comma/bullet item lists, including distance quantities such as `1 km corsa` and `1km di corsa`.
- Added circuit-level rest extraction for phrases like `recupero 1 minuto`, `con 1 minuto di recupero`, `1 min rest`, and `rec. 60s`.
- Preserved the existing rigid `1 Mu / 12 Bar Dip / 8 Pull Up / x4` circuit format.
- Kept circuit `rest` when saving/loading workout blocks so parsed recovery metadata is not discarded.
- Added parser regression examples for the supported natural circuit formats.

## Workout parser AI fallback scaffold

- Added a backend-only AI fallback stub for the workout parser; no real provider calls or frontend API keys were introduced.
- Added confidence/fallback evaluation for empty deterministic parses and advanced keywords such as `circuito`, `superset`, `emom`, and `amrap`.
- Added sanitization for future AI JSON responses, including field allowlisting, safe defaults, day clamping, and block validation.
- Documented the expected JSON schema for exercise, circuit, superset, rest, and multi-day responses in `docs/workout-parser-ai-fallback.md`.
- Added fallback tests/examples for natural circuit, AMRAP, and superset phrases.

## Workout parser circuit rest regression

- Fixed circuit recovery phrases such as `con un recupero di 3 minuti per serie` so they are stored as circuit `rest` metadata instead of becoming a bogus exercise.
- Added rest-only fragment handling for `recupero 3 minuti`, `recupero di 3 minuti`, `con recupero 3 minuti`, `3 minuti recupero`, `rest 180s`, `rec. 3'`, and per-giro/per-round/per-serie wording.
- Added inline natural circuit parsing for compact text like `circuito 4 giri 10 push up 5 burpees 10 pull up recupero 3 minuti`.
- Added parser regression tests for recupero per giro, per round, per serie, and finale.
- Rendered circuit `rest` in the shared circuit summary so parser preview and current workout cards show recovery metadata when present.
