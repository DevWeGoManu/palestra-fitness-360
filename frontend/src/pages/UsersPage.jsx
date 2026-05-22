import { useEffect, useState } from 'react';
import { Search } from 'lucide-react';
import { api } from '../api.js';
import { Loading } from '../components/Loading.jsx';
import { go } from '../utils/router.js';

const statuses = ['', 'pending', 'active', 'disabled'];

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
      <div className="page-title"><h2>Utenti</h2></div>
      <div className="toolbar">
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
          {statuses.map((status) => <option key={status} value={status}>{status || 'Tutti gli status'}</option>)}
        </select>
      </div>
      <form className="toolbar search-toolbar" onSubmit={applySearch}>
        <input placeholder="Cerca per nome, email o ruolo" value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
        <button className="primary"><Search size={18} /> Cerca</button>
      </form>
      {loading ? <Loading /> : (
        <div className="list">
          {filteredUsers.map((item) => (
            <button className="list-row" key={item.id} onClick={() => go(`/user?id=${item.id}`)}>
              <span><strong>{item.full_name}</strong><small>{item.email}</small></span>
              <span className="badge-stack"><span className="badge">{item.role}</span><span className={`badge status-${item.status}`}>{item.status}</span></span>
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
