export function routeFromHash() {
  const hash = window.location.hash.replace(/^#/, '') || '/';
  const [path, query = ''] = hash.split('?');
  return { path, params: new URLSearchParams(query) };
}

export function go(path) {
  window.location.hash = path;
}

export function canManage(user) {
  return ['admin', 'istruttore'].includes(user?.role);
}

export function canSelfManageWorkouts(user) {
  return user?.role === 'autonomo';
}

export function canEditWorkouts(user) {
  return canManage(user) || canSelfManageWorkouts(user);
}

export function isAdmin(user) {
  return user?.role === 'admin';
}
