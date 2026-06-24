export const AUTH_SESSION_CLEARED_EVENT = 'galotxas:auth-session-cleared';

export const getStoredAuthToken = () => localStorage.getItem('token');

export const clearStoredAuth = () => {
  localStorage.removeItem('token');
  localStorage.removeItem('user');
};

export const clearAuthSession = (reason) => {
  clearStoredAuth();

  window.dispatchEvent(new CustomEvent(AUTH_SESSION_CLEARED_EVENT, {
    detail: { reason }
  }));
};
