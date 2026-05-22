const API_BASE = import.meta.env.VITE_API_BASE || '/api';
let csrfToken = '';

export function setCsrfToken(token) {
  csrfToken = token || '';
}

async function request(path, options = {}) {
  const method = options.method || 'GET';
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };
  if (!['GET', 'HEAD'].includes(method.toUpperCase()) && csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers,
    ...options
  });

  const data = await response.json().catch(() => ({}));
  if (data.csrf_token) {
    setCsrfToken(data.csrf_token);
  }
  if (!response.ok) {
    throw new Error(data.error || 'Errore API');
  }
  return data;
}

export const api = {
  setCsrfToken,
  me: () => request('/auth/me.php'),
  login: (email, password) => request('/auth/login.php', {
    method: 'POST',
    body: JSON.stringify({ email, password })
  }),
  register: (payload) => request('/auth/register.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  verifyEmail: (token) => request(`/auth/verify-email.php?token=${encodeURIComponent(token)}`),
  requestPasswordReset: (email) => request('/auth/request-password-reset.php', {
    method: 'POST',
    body: JSON.stringify({ email })
  }),
  resetPassword: (payload) => request('/auth/reset-password.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  logout: () => request('/auth/logout.php', { method: 'POST' }),
  users: () => request('/users/index.php'),
  user: (id) => request(`/users/show.php?id=${id}`),
  createUser: (payload) => request('/users/index.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  updateUser: (id, payload) => request(`/users/show.php?id=${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload)
  }),
  deleteUser: (id) => request(`/users/show.php?id=${id}`, { method: 'DELETE' }),
  plans: () => request('/workouts/index.php'),
  createPlan: (payload) => request('/workouts/index.php', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  plan: (id) => request(`/workouts/show.php?id=${id}`),
  savePlan: (id, payload) => request(`/workouts/show.php?id=${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload)
  }),
  deletePlan: (id) => request(`/workouts/show.php?id=${id}`, { method: 'DELETE' }),
  parseWorkoutText: (text) => request('/workouts/parse.php', {
    method: 'POST',
    body: JSON.stringify({ text })
  }),
  sessions: (userId) => request(`/sessions/index.php${userId ? `?user_id=${userId}` : ''}`),
  completeWorkout: (workoutPlanId, workoutDayId) => request('/sessions/index.php', {
    method: 'POST',
    body: JSON.stringify({ workout_plan_id: workoutPlanId, workout_day_id: workoutDayId })
  }),
  saveExerciseNote: (exerciseId, note) => request('/exercise-notes/index.php', {
    method: 'POST',
    body: JSON.stringify({ exercise_id: exerciseId, note })
  }),
  dashboardStats: () => request('/dashboard/stats.php')
};
