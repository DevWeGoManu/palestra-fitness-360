import { useEffect, useMemo, useState } from 'react';
import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { canManage, canSelfManageWorkouts, go } from '../utils/router.js';

export function Workouts({ user, notify }) {
  const [plans, setPlans] = useState([]);
  const [users, setUsers] = useState([]);
  const [newPlan, setNewPlan] = useState({ assigned_user_id: '' });
  const [athleteFilterId, setAthleteFilterId] = useState('');
  const [athleteQuery, setAthleteQuery] = useState('');
  const [athleteMenuOpen, setAthleteMenuOpen] = useState(false);
  const [activeAthleteIndex, setActiveAthleteIndex] = useState(0);
  const [loading, setLoading] = useState(true);
  const athletes = useMemo(() => users.filter((u) => ['atleta', 'autonomo'].includes(u.role)), [users]);
  const selectedAthlete = useMemo(() => athletes.find((item) => Number(item.id) === Number(athleteFilterId)), [athletes, athleteFilterId]);
  const newPlanAthlete = useMemo(() => athletes.find((item) => Number(item.id) === Number(newPlan.assigned_user_id)), [athletes, newPlan.assigned_user_id]);
  const filteredAthletes = useMemo(() => {
    const query = athleteQuery.trim().toLowerCase();
    if (!query) return athletes;
    return athletes.filter((athlete) => athlete.full_name.toLowerCase().includes(query) || athlete.email?.toLowerCase().includes(query));
  }, [athletes, athleteQuery]);
  const filteredPlans = useMemo(() => {
    if (!athleteFilterId) return plans;
    return plans.filter((plan) => Number(plan.assigned_user_id) === Number(athleteFilterId));
  }, [plans, athleteFilterId]);

  async function load() {
    setLoading(true);
    try {
      const planData = await api.plans();
      setPlans(planData.plans);
      if (!canManage(user) && planData.plans.length > 0) {
        go(`/plan?id=${planData.plans[0].id}`);
        return;
      }
      if (canManage(user)) {
        const userData = await api.users();
        setUsers(userData.users);
      }
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, [user.id]);

  async function createPlan(event) {
    event.preventDefault();
    if (canManage(user) && !newPlan.assigned_user_id) return;
    try {
      const data = await api.createPlan({
        assigned_user_id: canManage(user) ? newPlan.assigned_user_id : user.id,
        name: newPlanAthlete ? newPlanAthlete.full_name : user.full_name
      });
      notify('Programma creato');
      go(`/plan?id=${data.id}`);
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  function selectAthlete(athlete) {
    setAthleteFilterId(athlete.id);
    setNewPlan({ assigned_user_id: athlete.id });
    setAthleteQuery(athlete.full_name);
    setAthleteMenuOpen(false);
    setActiveAthleteIndex(0);
  }

  function resetAthleteFilter() {
    setAthleteFilterId('');
    setNewPlan({ assigned_user_id: '' });
    setAthleteQuery('');
    setAthleteMenuOpen(false);
    setActiveAthleteIndex(0);
  }

  async function deletePlan(event, plan) {
    event.stopPropagation();
    if (!window.confirm(`Eliminare definitivamente "${plan.name}"?`)) return;
    try {
      await api.deletePlan(plan.id);
      setPlans((current) => current.filter((item) => item.id !== plan.id));
      notify('Programma eliminato');
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  return (
    <section className="page">
      <div className="page-title row">
        <div>
          <h2>Lista allenamenti</h2>
          <p>Gestisci schede esistenti e crea nuovi programmi per gli atleti.</p>
        </div>
        <div className="users-summary" aria-live="polite">
          {filteredPlans.length} schede
        </div>
      </div>
      {canManage(user) && (
        <div className={['panel-form', 'workouts-toolbar', athleteMenuOpen ? 'is-open' : ''].filter(Boolean).join(' ')}>
          <div className="athlete-combobox">
            <label htmlFor="workout-athlete-search">Seleziona atleta</label>
            <div className="athlete-combobox-field">
              <input
                id="workout-athlete-search"
                type="search"
                role="combobox"
                aria-expanded={athleteMenuOpen}
                aria-controls="workout-athlete-options"
                aria-activedescendant={athleteMenuOpen && filteredAthletes[activeAthleteIndex] ? `workout-athlete-option-${filteredAthletes[activeAthleteIndex].id}` : undefined}
                aria-autocomplete="list"
                autoComplete="off"
                placeholder="Cerca atleta"
                value={athleteQuery}
                onFocus={() => setAthleteMenuOpen(true)}
                onBlur={() => window.setTimeout(() => setAthleteMenuOpen(false), 120)}
                onChange={(event) => {
                  setAthleteQuery(event.target.value);
                  setAthleteFilterId('');
                  setNewPlan({ assigned_user_id: '' });
                  setAthleteMenuOpen(true);
                  setActiveAthleteIndex(0);
                }}
                onKeyDown={(event) => {
                  if (event.key === 'Escape') {
                    setAthleteMenuOpen(false);
                    return;
                  }
                  if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setAthleteMenuOpen(true);
                    setActiveAthleteIndex((current) => Math.min(current + 1, Math.max(filteredAthletes.length - 1, 0)));
                    return;
                  }
                  if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActiveAthleteIndex((current) => Math.max(current - 1, 0));
                    return;
                  }
                  if (event.key === 'Enter' && athleteMenuOpen && filteredAthletes[activeAthleteIndex]) {
                    event.preventDefault();
                    selectAthlete(filteredAthletes[activeAthleteIndex]);
                    return;
                  }
                  if (event.key === 'Enter') {
                    event.preventDefault();
                  }
                }}
              />
              {selectedAthlete && (
                <button className="athlete-clear" type="button" onClick={resetAthleteFilter} aria-label="Mostra tutti gli atleti">
                  Tutti
                </button>
              )}
            </div>
            {athleteMenuOpen && (
              <div className="athlete-combobox-options" id="workout-athlete-options" role="listbox">
                {filteredAthletes.map((athlete, index) => (
                  <button
                    id={`workout-athlete-option-${athlete.id}`}
                    key={athlete.id}
                    type="button"
                    role="option"
                    aria-selected={Number(athlete.id) === Number(athleteFilterId)}
                    className={index === activeAthleteIndex ? 'active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => selectAthlete(athlete)}
                    onMouseEnter={() => setActiveAthleteIndex(index)}
                  >
                    <strong>{athlete.full_name}</strong>
                    {athlete.email && <span>{athlete.email}</span>}
                  </button>
                ))}
                {filteredAthletes.length === 0 && <span className="athlete-combobox-empty">Nessun atleta trovato</span>}
              </div>
            )}
          </div>
          <form onSubmit={createPlan}>
            <button className="primary" disabled={canManage(user) && !newPlan.assigned_user_id}><Plus size={18} /> Nuovo</button>
          </form>
        </div>
      )}
      {loading ? <Loading /> : (
        <div className="list workout-list">
          {filteredPlans.map((plan) => (
            <div className="list-row workout-row" role="button" tabIndex="0" key={plan.id} onClick={() => go(`/plan?id=${plan.id}`)} onKeyDown={(event) => { if (event.key === 'Enter') go(`/plan?id=${plan.id}`); }}>
              <span className="workout-row-main">
                <span className="workout-row-icon" aria-hidden="true"><CalendarDays size={18} /></span>
                <span>
                  <strong>{plan.assigned_user_name || plan.name}</strong>
                  <small>Scheda allenamento</small>
                  <span className="workout-row-date">{new Date(plan.created_at).toLocaleDateString('it-IT')}</span>
                </span>
              </span>
              {canManage(user) && (
                <button className="ghost danger row-action" type="button" onClick={(event) => deletePlan(event, plan)}>
                  <Trash2 size={16} /> Elimina
                </button>
              )}
            </div>
          ))}
          {filteredPlans.length === 0 && (
            <div className="empty empty-card">
              <strong>Nessun allenamento ancora</strong>
              <span>{canSelfManageWorkouts(user) ? 'Apri Crea scheda per iniziare.' : canManage(user) && selectedAthlete ? 'Nessuna scheda collegata a questo atleta.' : canManage(user) ? 'Crea una nuova scheda per iniziare.' : 'Il tuo coach non ha ancora assegnato una scheda.'}</span>
            </div>
          )}
        </div>
      )}
    </section>
  );
}
