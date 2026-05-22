import React from 'react';
import { CalendarDays, LogOut, Menu, UserCircle, Users, X } from 'lucide-react';
import { canManage, go } from '../utils/router.js';

export function Shell({ user, onLogout, children }) {
  const [open, setOpen] = React.useState(false);
  const currentPath = (window.location.hash.replace(/^#/, '').split('?')[0] || '/');
  const nav = canManage(user)
    ? [
        { href: '/users', label: 'Utenti', icon: Users },
        { href: '/workouts', label: 'Allenamenti', icon: CalendarDays },
        { href: `/user?id=${user.id}`, label: 'Profilo', icon: UserCircle }
      ]
    : [
        { href: '/workouts', label: 'Allenamenti', icon: CalendarDays },
        { href: `/user?id=${user.id}`, label: 'Profilo', icon: UserCircle }
      ];

  return (
    <div className="app-shell">
      <aside className={`sidebar ${open ? 'open' : ''}`}>
        <div className="sidebar-head"><img src={`${import.meta.env.BASE_URL}images/logo.png`} alt="" /><strong>Palestra Fitness 360</strong></div>
        <nav aria-label="Navigazione principale">
          {nav.map((item) => {
            const Icon = item.icon;
            const itemPath = item.href.split('?')[0];
            return (
              <button
                key={item.href}
                className={currentPath === itemPath ? 'active' : ''}
                aria-label={item.label}
                onClick={() => { go(item.href); setOpen(false); }}
              >
                <Icon size={19} />{item.label}
              </button>
            );
          })}
        </nav>
        <button className="ghost logout" aria-label="Esci" onClick={onLogout}><LogOut size={18} /> Esci</button>
      </aside>
      <div className="main-area">
        <header className="topbar">
          <button className="icon-button mobile-only" onClick={() => setOpen(true)} aria-label="Apri menu"><Menu /></button>
          <div><strong>{user.full_name}</strong><span>{user.role}</span></div>
        </header>
        {open && <button className="overlay" onClick={() => setOpen(false)} aria-label="Chiudi menu"><X /></button>}
        {children}
      </div>
    </div>
  );
}
