import { CalendarDays, CheckCircle2, Dumbbell, Users } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { canManage, go } from '../utils/router.js';
import { useAsync } from '../hooks/useAsync.js';

export function Dashboard({ user }) {
  const { data, loading } = useAsync(() => api.dashboardStats(), [user.id]);
  const stats = data?.stats;

  return (
    <section className="page">
      <div className="page-title">
        <h2>Dashboard</h2>
        <p>{canManage(user) ? 'Vista operativa su atleti e programmi.' : 'Il riepilogo dei tuoi allenamenti.'}</p>
      </div>
      {loading && <Loading />}
      {!loading && canManage(user) && (
        <>
          <div className="metric-grid">
            <button onClick={() => go('/users')} className="metric"><Users /><strong>{stats?.athletes || 0}</strong><span>Atleti</span></button>
            <button onClick={() => go('/workouts')} className="metric"><Dumbbell /><strong>{stats?.active_plans || 0}</strong><span>Programmi attivi</span></button>
          </div>
          <h3>Ultimi allenamenti completati</h3>
          <div className="list">
            {(stats?.recent_sessions || []).map((session) => (
              <div className="list-row static" key={`${session.full_name}-${session.completed_at}`}>
                <span><strong>{session.full_name}</strong><small>{session.workout_plan_name}{session.workout_day_title ? ` - ${session.workout_day_title}` : ''}</small></span>
                <span>{new Date(session.completed_at).toLocaleString('it-IT')}</span>
              </div>
            ))}
            {(stats?.recent_sessions || []).length === 0 && (
              <div className="empty empty-card">
                <strong>Nessun allenamento completato</strong>
                <span>Non ci sono sessioni recenti da mostrare.</span>
              </div>
            )}
          </div>
        </>
      )}
      {!loading && !canManage(user) && (
        <div className="metric-grid">
          <button onClick={() => go('/workouts')} className="metric"><CalendarDays /><strong>{stats?.active_plan?.name || 'Nessuno'}</strong><span>Programma attivo</span></button>
          <div className="metric"><CheckCircle2 /><strong>{stats?.completed_count || 0}</strong><span>Allenamenti completati</span></div>
          <div className="metric"><Dumbbell /><strong>{stats?.last_session ? new Date(stats.last_session.completed_at).toLocaleDateString('it-IT') : 'Mai'}</strong><span>{stats?.last_session?.workout_day_title || 'Ultimo allenamento'}</span></div>
        </div>
      )}
    </section>
  );
}
