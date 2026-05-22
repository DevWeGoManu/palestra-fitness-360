import { useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';

export function ResetPassword({ token, notify }) {
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    try {
      await api.resetPassword({ token, password, password_confirm: passwordConfirm });
      notify('Password aggiornata');
      go('/');
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
        <h1>Nuova password</h1>
        <label>Password<input type="password" minLength="8" value={password} onChange={(e) => setPassword(e.target.value)} required /></label>
        <label>Conferma password<input type="password" minLength="8" value={passwordConfirm} onChange={(e) => setPasswordConfirm(e.target.value)} required /></label>
        <button className="primary" disabled={loading}>{loading ? 'Salvataggio...' : 'Aggiorna password'}</button>
      </form>
    </main>
  );
}
