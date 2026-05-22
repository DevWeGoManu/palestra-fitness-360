import { useEffect, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { canEditWorkouts, canManage, canSelfManageWorkouts, go } from '../utils/router.js';

export function Workouts({ user, notify }) {
  const [plans, setPlans] = useState([]);
  const [users, setUsers] = useState([]);
  const [newPlan, setNewPlan] = useState({ assigned_user_id: '' });
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const planData = await api.plans();
      setPlans(planData.plans);
      if (!canEditWorkouts(user) && planData.plans.length > 0) {
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
    const athlete = users.find((item) => Number(item.id) === Number(newPlan.assigned_user_id));
    try {
      const data = await api.createPlan({
        assigned_user_id: canManage(user) ? newPlan.assigned_user_id : user.id,
        name: athlete ? athlete.full_name : user.full_name
      });
      notify('Programma creato');
      go(`/plan?id=${data.id}`);
    } catch (err) {
      notify(err.message, 'error');
    }
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
        <div><h2>Lista allenamenti</h2></div>
      </div>
      {canManage(user) && (
        <form className="toolbar" onSubmit={createPlan}>
          <select value={newPlan.assigned_user_id} onChange={(e) => setNewPlan({ ...newPlan, assigned_user_id: e.target.value })} required>
            <option value="" disabled>Seleziona atleta</option>
            {users.filter((u) => ['atleta', 'autonomo'].includes(u.role)).map((u) => <option key={u.id} value={u.id}>{u.full_name}</option>)}
          </select>
          <button className="primary"><Plus size={18} /> Nuovo</button>
        </form>
      )}
      {canSelfManageWorkouts(user) && (
        <form className="toolbar compact-toolbar" onSubmit={createPlan}>
          <button className="primary"><Plus size={18} /> Nuova scheda</button>
        </form>
      )}
      {loading ? <Loading /> : (
        <div className="list">
          {plans.map((plan) => (
            <div className="list-row" role="button" tabIndex="0" key={plan.id} onClick={() => go(`/plan?id=${plan.id}`)} onKeyDown={(event) => { if (event.key === 'Enter') go(`/plan?id=${plan.id}`); }}>
              <span><strong>{plan.assigned_user_name || plan.name}</strong></span>
              <span>{new Date(plan.created_at).toLocaleDateString('it-IT')}</span>
              {canEditWorkouts(user) && (
                <button className="ghost danger row-action" type="button" onClick={(event) => deletePlan(event, plan)}>
                  <Trash2 size={16} /> Elimina
                </button>
              )}
            </div>
          ))}
          {plans.length === 0 && <p className="empty">Nessun programma disponibile.</p>}
        </div>
      )}
    </section>
  );
}
