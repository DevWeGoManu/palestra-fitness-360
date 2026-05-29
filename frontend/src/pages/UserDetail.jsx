import { useEffect, useState } from 'react';
import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { canManage, go, isAdmin } from '../utils/router.js';

export function UserDetail({ id, currentUser, onUserUpdate, notify }) {
  const [data, setData] = useState(null);
  const [form, setForm] = useState(null);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    try {
      const result = await api.user(id);
      setData(result);
      setForm({ ...result.user, password: '' });
      if (Number(currentUser.id) === Number(id)) {
        onUserUpdate(result.user);
      }
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, [id]);

  async function save(event) {
    event.preventDefault();
    const payload = {
      full_name: form.full_name,
      email: form.email
    };

    if (isAdmin(currentUser)) {
      payload.role = form.role;
      payload.status = form.status;
    }

    try {
      await api.updateUser(id, payload);
      notify('Utente aggiornato');
      load();
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  async function setStatus(status) {
    try {
      await api.updateUser(id, { ...form, status, password: '' });
      notify('Status aggiornato');
      load();
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  async function remove() {
    if (!window.confirm('Eliminare definitivamente questo utente?')) return;
    try {
      await api.deleteUser(id);
      notify('Utente eliminato');
      go('/users');
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  async function createPlan() {
    try {
      const result = await api.createPlan({
        assigned_user_id: id,
        name: `Scheda ${data.user.full_name}`
      });
      notify('Scheda creata');
      go(`/plan?id=${result.id}`);
    } catch (err) {
      notify(err.message, 'error');
    }
  }

  if (loading || !data || !form) return <section className="page"><Loading /></section>;

  const isSelf = Number(currentUser.id) === Number(id);
  const isCurrentAdmin = isAdmin(currentUser);
  const canSeeProgramming = canManage(currentUser) && ['atleta', 'autonomo'].includes(data.user.role);
  const canDeleteUser = isCurrentAdmin && !isSelf;
  const canEditProfile = isSelf || isCurrentAdmin;
  const showAssignedPlans = canManage(currentUser) && !canSeeProgramming;
  const statusLabels = { active: 'Attivo', pending: 'In attesa', disabled: 'Disabilitato' };
  const roleLabels = { atleta: 'Atleta', autonomo: 'Autonomo', istruttore: 'Istruttore', admin: 'Admin' };

  return (
    <section className="page">
      <div className="page-title row">
        <div className="profile-heading">
          <h2>{data.user.full_name}</h2>
          <span className={`status-dot-label status-${data.user.status}`}><span aria-hidden="true" />Status: {statusLabels[data.user.status] || data.user.status}</span>
        </div>
        <div className="profile-title-actions">
          {canDeleteUser && <button className="ghost danger" onClick={remove}><Trash2 size={17} /> Elimina</button>}
        </div>
      </div>

      <div className="user-detail-actions row">
        <div className="user-detail-badges">
          <span className={`badge role-badge`}>{roleLabels[data.user.role] || data.user.role}</span>
          <span className={`badge status-${data.user.status}`}>{statusLabels[data.user.status] || data.user.status}</span>
        </div>
      </div>

      <section className="user-info-card" aria-label="Informazioni utente">
        <div className="user-info-main">
          <span className="user-info-avatar" aria-hidden="true">{getInitials(data.user.full_name)}</span>
          <div>
            <strong>{data.user.full_name}</strong>
            <span>{data.user.email}</span>
          </div>
        </div>
        <div className="user-info-grid">
          <div>
            <small>Ruolo</small>
            <strong>{roleLabels[data.user.role] || data.user.role}</strong>
          </div>
          <div>
            <small>Status</small>
            <strong><span className={`status-dot status-${data.user.status}`} aria-hidden="true" />{statusLabels[data.user.status] || data.user.status}</strong>
          </div>
        </div>
      </section>

      {canSeeProgramming && (
        <section className="panel-form programming-panel">
          <div>
            <h3>Programmazione</h3>
            <p className="muted">Apri la scheda dell'atleta e modifica l'allenamento del giorno.</p>
          </div>
          {data.plans.length > 0 ? (
            <button className="primary" type="button" onClick={() => go(`/plan?id=${data.plans[0].id}`)}><CalendarDays size={18} /> Apri scheda</button>
          ) : (
            <button className="primary" type="button" onClick={createPlan}><Plus size={18} /> Crea scheda</button>
          )}
        </section>
      )}

      {canEditProfile && (
        <form className="panel-form" onSubmit={save}>
          <label>
            <span>Nome completo</span>
            <input value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} required />
          </label>
          <label>
            <span>Email</span>
            <input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required />
          </label>
          {isCurrentAdmin ? (
            <>
              <label>
                <span>Ruolo</span>
                <select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
                  <option value="atleta">Atleta</option>
                  <option value="autonomo">Autonomo</option>
                  <option value="istruttore">Istruttore</option>
                  <option value="admin">Admin</option>
                </select>
              </label>
              <label>
                <span>Status</span>
                <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
                  <option value="pending">Pending</option>
                  <option value="active">Active</option>
                  <option value="disabled">Disabled</option>
                </select>
              </label>
            </>
          ) : null}
          <button className="primary">Salva utente</button>
        </form>
      )}

      {isCurrentAdmin && !isSelf && (
        <div className="toolbar wrap">
          <button className="ghost" onClick={() => setStatus('active')}>Approva/Riattiva</button>
          <button className="ghost danger" onClick={() => setStatus('disabled')}>Disabilita</button>
        </div>
      )}

      {showAssignedPlans && (
        <>
          <h3>Schede assegnate</h3>
          <div className="list">
            {data.plans.map((plan) => <button key={plan.id} className="list-row" onClick={() => go(`/plan?id=${plan.id}`)}><strong>{plan.name}</strong><span>{new Date(plan.created_at).toLocaleDateString('it-IT')}</span></button>)}
            {data.plans.length === 0 && (
              <div className="empty empty-card">
                <strong>Nessuna scheda assegnata</strong>
                <span>Il profilo non ha ancora programmi collegati.</span>
              </div>
            )}
          </div>
        </>
      )}
    </section>
  );
}

function getInitials(name = '') {
  const initials = name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase();
  return initials || 'U';
}
