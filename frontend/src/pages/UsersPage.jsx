import { useEffect, useState } from 'react';
import { Search } from 'lucide-react';
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
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');

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

  function applySearch(event) {
    event.preventDefault();
    setSearch(searchInput.trim().toLowerCase());
  }

  const filteredUsers = users.filter((item) => {
    const matchesStatus = !statusFilter || item.status === statusFilter;
    const haystack = `${item.full_name} ${item.email} ${item.role} ${item.status}`.toLowerCase();
    const matchesSearch = !search || haystack.includes(search);
    return matchesStatus && matchesSearch;
  });

  return (
    <section className="page">
      <div className="page-title">
        <h2>Utenti</h2>
        <p>Gestisci profili, status e programmazione degli atleti.</p>
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

        <form className="users-search" onSubmit={applySearch}>
          <label className="sr-only" htmlFor="users-search">Cerca utenti</label>
          <input
            id="users-search"
            placeholder="Cerca per nome, email o ruolo"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
          />
          <button className="primary" type="submit"><Search size={18} /> Cerca</button>
        </form>
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
