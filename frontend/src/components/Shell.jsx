import React from 'react';
import { CalendarDays, FilePlus2, LifeBuoy, LogOut, Menu, UserCircle, Users, X } from 'lucide-react';
import { canManage, canSelfManageWorkouts, go } from '../utils/router.js';

export function Shell({ user, onLogout, children }) {
  const [open, setOpen] = React.useState(false);
  const currentPath = (window.location.hash.replace(/^#/, '').split('?')[0] || '/');
  const logoSrc = `${import.meta.env.BASE_URL}icons/icon-192.png`;
  const nav = canManage(user)
    ? [
        { href: '/users', label: 'Utenti', icon: Users },
        { href: '/workouts', label: 'Allenamenti', icon: CalendarDays },
        { href: `/user?id=${user.id}`, label: 'Profilo', icon: UserCircle }
      ]
    : [
        { href: '/workouts', label: 'Allenamenti', icon: CalendarDays },
        ...(canSelfManageWorkouts(user) ? [{ href: '/create-plan', label: 'Crea scheda', icon: FilePlus2 }] : []),
        { href: `/user?id=${user.id}`, label: 'Profilo', icon: UserCircle }
      ];

  React.useEffect(() => {
    if (!open) return undefined;
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') setOpen(false);
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [open]);

  return (
    <div className="app-shell">
      <aside id="primary-sidebar" className={`sidebar ${open ? 'open' : ''}`}>
        <div className="sidebar-head">
          <img src={logoSrc} alt="" aria-hidden="true" />
          <strong>AthleoDesk</strong>
        </div>
        <nav aria-label="Navigazione principale">
          {nav.map((item) => {
            const Icon = item.icon;
            const itemPath = item.href.split('?')[0];
            const active = currentPath === itemPath;
            return (
              <button
                key={item.href}
                type="button"
                className={active ? 'active' : ''}
                aria-current={active ? 'page' : undefined}
                aria-label={item.label}
                onClick={() => { go(item.href); setOpen(false); }}
              >
                <Icon size={19} />{item.label}
              </button>
            );
          })}
        </nav>
        <button
          type="button"
          className={currentPath === '/tickets' ? 'active sidebar-ticket' : 'sidebar-ticket'}
          aria-current={currentPath === '/tickets' ? 'page' : undefined}
          aria-label="Ticket"
          onClick={() => { go('/tickets'); setOpen(false); }}
        >
          <LifeBuoy size={19} /> Ticket
        </button>
        <button type="button" className="ghost logout" aria-label="Esci" onClick={onLogout}><LogOut size={18} /> Esci</button>
      </aside>
      <div className="main-area">
        <header className="topbar">
          <button
            type="button"
            className="icon-button mobile-only"
            onClick={() => setOpen(true)}
            aria-label="Apri menu"
            aria-controls="primary-sidebar"
            aria-expanded={open}
          >
            <Menu />
          </button>
          <div><strong>{user.full_name}</strong><span>{user.role}</span></div>
        </header>
        {open && <button type="button" className="overlay" onClick={() => setOpen(false)} aria-label="Chiudi menu"><X /></button>}
        {children}
      </div>
    </div>
  );
}
