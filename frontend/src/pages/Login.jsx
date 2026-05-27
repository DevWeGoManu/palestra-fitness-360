import { useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';
import pkg from '../../package.json';

export function Login({ onLogin, notify }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function submit(event) {
    event.preventDefault();
    setError('');
    setLoading(true);
    try {
      const data = await api.login(email, password);
      onLogin(data.user);
      go(['admin', 'istruttore'].includes(data.user.role) ? '/users' : '/workouts');
      notify('Accesso effettuato');
    } catch (err) {
      const message = /troppi tentativi/i.test(err.message || '')
        ? 'Troppi tentativi. Attendi qualche minuto e riprova.'
        : 'Accesso non riuscito. Controlla le credenziali e riprova.';
      setError(message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-page">
      <form className="login-panel" onSubmit={submit} aria-busy={loading}>
        <div className="login-brand">
          <span className="login-monogram" aria-hidden="true">AD</span>
          <div>
            <h1>AthleoDesk</h1>
            <p className="login-subtitle">Area riservata per coach e atleti</p>
          </div>
        </div>

        {error && (
          <p className="login-error" role="alert">
            {error}
          </p>
        )}

        <label htmlFor="login-email">
          Email
          <input
            id="login-email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            type="email"
            inputMode="email"
            autoComplete="email"
            required
            disabled={loading}
          />
        </label>
        <label htmlFor="login-password">
          Password
          <input
            id="login-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            type="password"
            autoComplete="current-password"
            required
            minLength={1}
            disabled={loading}
          />
        </label>
        <button type="submit" className="primary login-submit" disabled={loading}>
          {loading ? 'Accesso in corso...' : 'Accedi'}
        </button>
        <div className="auth-links">
          <button type="button" className="link-button" onClick={() => go('/register')}>Registrati</button>
          <button type="button" className="link-button" onClick={() => go('/forgot-password')}>Password dimenticata?</button>
        </div>
        <footer className="login-footer">
          <span>Privacy</span>
          <span>Contatti: assistenza</span>
          <span>v{pkg.version}</span>
        </footer>
      </form>
    </main>
  );
}
