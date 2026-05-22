import { useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';

export function ForgotPassword({ notify }) {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    try {
      const response = await api.requestPasswordReset(email);
      setMessage(response.message);
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
        <h1>Password dimenticata</h1>
        {message && <p className="empty">{message}</p>}
        <label>Email<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></label>
        <button className="primary" disabled={loading}>{loading ? 'Invio...' : 'Invia link'}</button>
        <button type="button" className="link-button" onClick={() => go('/')}>Torna al login</button>
      </form>
    </main>
  );
}
