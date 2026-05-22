import { useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';

export function Register({ notify }) {
  const [form, setForm] = useState({ first_name: '', last_name: '', email: '', password: '', password_confirm: '', accepted_terms: false, website: '' });
  const [loading, setLoading] = useState(false);
  const [done, setDone] = useState(false);

  async function submit(event) {
    event.preventDefault();
    if (form.password.length < 8 || form.password !== form.password_confirm || !form.accepted_terms) {
      notify('Controlla password e accettazione privacy/termini', 'error');
      return;
    }
    setLoading(true);
    try {
      const response = await api.register(form);
      setDone(true);
      notify(response.message || 'Registrazione completata');
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-page">
      <form className="login-panel" onSubmit={submit}>
        <div className="brand-mark"><img src={`${import.meta.env.BASE_URL}images/logo.png`} alt="" /></div>
        <h1>Registrati</h1>
        {done ? (
          <>
            <p className="empty">Controlla la tua email per verificare l account. Dopo la verifica dovrai attendere approvazione.</p>
            <button type="button" className="primary" onClick={() => go('/')}>Torna al login</button>
          </>
        ) : (
          <>
            <input className="hp-field" tabIndex="-1" autoComplete="off" value={form.website} onChange={(e) => setForm({ ...form, website: e.target.value })} />
            <label>Nome<input value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} required /></label>
            <label>Cognome<input value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} required /></label>
            <label>Email<input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required /></label>
            <label>Password<input type="password" minLength="8" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required /></label>
            <label>Conferma password<input type="password" minLength="8" value={form.password_confirm} onChange={(e) => setForm({ ...form, password_confirm: e.target.value })} required /></label>
            <label className="check-row"><input type="checkbox" checked={form.accepted_terms} onChange={(e) => setForm({ ...form, accepted_terms: e.target.checked })} /> Accetto privacy e termini</label>
            <button className="primary" disabled={loading}>{loading ? 'Invio...' : 'Crea account'}</button>
            <button type="button" className="link-button" onClick={() => go('/')}>Ho gia un account</button>
          </>
        )}
      </form>
    </main>
  );
}
