import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { go } from '../utils/router.js';

export function VerifyEmail({ token, notify }) {
  const [message, setMessage] = useState('Verifica in corso...');

  useEffect(() => {
    api.verifyEmail(token)
      .then((response) => setMessage(response.message || 'Email verificata'))
      .catch((err) => {
        setMessage(err.message);
        notify(err.message, 'error');
      });
  }, [token]);

  return (
    <main className="login-page">
      <div className="login-panel">
        <h1>Verifica email</h1>
        <p className="empty">{message}</p>
        <button type="button" className="primary" onClick={() => go('/')}>Vai al login</button>
      </div>
    </main>
  );
}
