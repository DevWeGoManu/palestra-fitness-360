import { useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';

export function Login({ onLogin, notify }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    try {
      const data = await api.login(email, password);
      onLogin(data.user);
      go(['admin', 'istruttore'].includes(data.user.role) ? '/users' : '/workouts');
      notify('Accesso effettuato');
    } catch (err) {
      notify(err.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-page">
      <form className="login-panel" onSubmit={submit}>
        <h1>AthleoDesk</h1>
        <p className="login-subtitle">Area riservata per coach e atleti</p>
        <label>Email<input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required /></label>
        <label>Password<input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required minLength={1} /></label>
        <button className="primary" disabled={loading}>{loading ? 'Accesso...' : 'Accedi'}</button>
        <div className="auth-links">
          <button type="button" className="link-button" onClick={() => go('/register')}>Registrati</button>
          <button type="button" className="link-button" onClick={() => go('/forgot-password')}>Password dimenticata?</button>
        </div>
      </form>
    </main>
  );
}
