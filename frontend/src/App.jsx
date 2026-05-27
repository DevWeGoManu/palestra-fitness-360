import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { api } from './api.js';
import { InstallAppBanner } from './components/InstallAppBanner.jsx';
import { Shell } from './components/Shell.jsx';
import { Toast } from './components/Toast.jsx';
import { Login } from './pages/Login.jsx';
import { ForgotPassword } from './pages/ForgotPassword.jsx';
import { Register } from './pages/Register.jsx';
import { ResetPassword } from './pages/ResetPassword.jsx';
import { VerifyEmail } from './pages/VerifyEmail.jsx';
import { canManage, routeFromHash } from './utils/router.js';

const PlanEditor = lazy(() => import('./pages/PlanEditor.jsx').then((module) => ({ default: module.PlanEditor })));
const UserDetail = lazy(() => import('./pages/UserDetail.jsx').then((module) => ({ default: module.UserDetail })));
const UsersPage = lazy(() => import('./pages/UsersPage.jsx').then((module) => ({ default: module.UsersPage })));
const Workouts = lazy(() => import('./pages/Workouts.jsx').then((module) => ({ default: module.Workouts })));
const Tickets = lazy(() => import('./pages/Tickets.jsx').then((module) => ({ default: module.Tickets })));
const CreatePlan = lazy(() => import('./pages/CreatePlan.jsx').then((module) => ({ default: module.CreatePlan })));

function App() {
  const [route, setRoute] = useState(routeFromHash());
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState(null);
  const toastTimer = useRef(null);

  function notify(message, type = 'success') {
    setToast({ message, type });
    window.clearTimeout(toastTimer.current);
    toastTimer.current = window.setTimeout(() => setToast(null), 3200);
  }

  useEffect(() => {
    const onHash = () => setRoute(routeFromHash());
    window.addEventListener('hashchange', onHash);
    api.me().then((data) => setUser(data.user)).finally(() => setLoading(false));
    return () => {
      window.removeEventListener('hashchange', onHash);
      window.clearTimeout(toastTimer.current);
    };
  }, []);

  if (loading) {
    return <div className="splash"><strong>AthleoDesk</strong><span>Caricamento</span></div>;
  }

  if (!user && route.path === '/register') {
    return <><Register notify={notify} /><InstallAppBanner /><Toast toast={toast} onClose={() => setToast(null)} /></>;
  }
  if (!user && route.path === '/forgot-password') {
    return <><ForgotPassword notify={notify} /><InstallAppBanner /><Toast toast={toast} onClose={() => setToast(null)} /></>;
  }
  if (!user && route.path === '/reset-password') {
    return <><ResetPassword token={route.params.get('token') || ''} notify={notify} /><InstallAppBanner /><Toast toast={toast} onClose={() => setToast(null)} /></>;
  }
  if (!user && route.path === '/verify-email') {
    return <><VerifyEmail token={route.params.get('token') || ''} notify={notify} /><InstallAppBanner /><Toast toast={toast} onClose={() => setToast(null)} /></>;
  }

  if (!user) {
    return <><Login onLogin={setUser} notify={notify} /><InstallAppBanner /><Toast toast={toast} onClose={() => setToast(null)} /></>;
  }

  return (
    <>
      <Shell user={user} onLogout={() => api.logout().finally(() => setUser(null))}>
        <Suspense fallback={<div className="loading">Caricamento pagina</div>}>
          {route.path === '/' && canManage(user) && <Workouts user={user} notify={notify} />}
          {route.path === '/' && !canManage(user) && <Workouts user={user} notify={notify} />}
          {route.path === '/workouts' && <Workouts user={user} notify={notify} />}
          {route.path === '/create-plan' && user.role === 'autonomo' && <CreatePlan user={user} notify={notify} />}
          {route.path === '/users' && canManage(user) && <UsersPage notify={notify} />}
          {route.path === '/tickets' && <Tickets notify={notify} />}
          {route.path === '/user' && (canManage(user) || Number(route.params.get('id')) === Number(user.id)) && (
            <UserDetail id={route.params.get('id')} currentUser={user} onUserUpdate={setUser} notify={notify} />
          )}
          {route.path === '/plan' && (
            <PlanEditor
              id={route.params.get('id')}
              user={user}
              notify={notify}
              editMode={route.params.get('edit') === '1'}
            />
          )}
        </Suspense>
      </Shell>
      <InstallAppBanner />
      <Toast toast={toast} onClose={() => setToast(null)} />
    </>
  );
}

export default App;
