import { useEffect, useState } from 'react';
import { Search, X } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { go } from '../utils/router.js';

const statuses = ['', 'pending', 'active', 'disabled'];
const statusLabels = {
  '': 'Tutti',
  pending: 'In attesa',
  active: 'Attivi',
  disabled: 'Disabilitati'
};
const roleLabels = {
  atleta: 'Atleta',
  autonomo: 'Autonomo',
  istruttore: 'Istruttore',
  admin: 'Admin'
};

export function UsersPage({ notify }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState('');

  async function load() {
    setLoading(true);
    try {
      const data = await api.users();
      setUsers(data.users);
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);
  useEffect(() => {
    const timeoutId = window.setTimeout(() => setDebouncedSearchQuery(searchQuery.trim().toLowerCase()), 280);
    return () => window.clearTimeout(timeoutId);
  }, [searchQuery]);

  const filteredUsers = users.filter((item) => {
    const matchesStatus = !statusFilter || item.status === statusFilter;
    const haystack = `${item.full_name} ${item.email} ${item.role}`.toLowerCase();
    const matchesSearch = !debouncedSearchQuery || haystack.includes(debouncedSearchQuery);
    return matchesStatus && matchesSearch;
  });

  return (
    <section className="page">
      <div className="page-title row">
        <div>
          <h2>Utenti</h2>
          <p>Gestisci profili, status e programmazione degli atleti.</p>
        </div>
        <div className="users-summary" aria-live="polite">
          {loading ? 'Caricamento utenti…' : `${filteredUsers.length} su ${users.length} utenti`}
        </div>
      </div>

      <div className="users-controls">
        <div className="users-status-filter" role="group" aria-label="Filtra utenti per status">
          {statuses.map((status) => (
            <button
              key={status}
              type="button"
              className={statusFilter === status ? 'active' : ''}
              aria-pressed={statusFilter === status}
              onClick={() => setStatusFilter(status)}
            >
              {statusLabels[status]}
            </button>
          ))}
        </div>

        <div className="users-search" role="search">
          <label className="sr-only" htmlFor="users-search">Cerca utenti</label>
          <div className="users-search-field">
            <Search size={17} aria-hidden="true" />
            <input
              id="users-search"
              placeholder="Cerca per nome, email o ruolo"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
            {searchQuery && (
              <button className="users-search-clear" type="button" onClick={() => setSearchQuery('')} aria-label="Pulisci ricerca">
                <X size={15} />
              </button>
            )}
          </div>
          {searchQuery && <button className="ghost" type="button" onClick={() => setSearchQuery('')}>Pulisci</button>}
        </div>
      </div>

      {loading ? <Loading /> : (
        <div className="list users-list">
          {filteredUsers.map((item) => (
            <button className="list-row user-card" key={item.id} onClick={() => go(`/user?id=${item.id}`)}>
              <span className="user-card-main">
                <span className="user-card-avatar" aria-hidden="true">{getInitials(item.full_name)}</span>
                <span>
                  <strong>{item.full_name}</strong>
                  <small>{item.email}</small>
                </span>
              </span>
              <span className="badge-stack user-card-badges">
                <span className="badge role-badge">{roleLabels[item.role] || item.role}</span>
                <span className={`badge status-${item.status}`}>{statusLabels[item.status] || item.status}</span>
              </span>
            </button>
          ))}
          {filteredUsers.length === 0 && (
            <div className="empty empty-card">
              <strong>Nessun utente trovato</strong>
              <span>Prova a cambiare filtro o termine di ricerca.</span>
            </div>
          )}
        </div>
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
