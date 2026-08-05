export const AUTH_SESSION_CLEARED_EVENT = 'galotxas:auth-session-cleared';
export const AUTH_TOKEN_STORAGE_KEY = 'token';
export const LEGACY_AUTH_USER_STORAGE_KEY = 'user';
export const INACTIVE_USER_AUTH_MESSAGE = 'El usuario está inactivo.';

export const shouldInvalidateAuthSession = (error) => {
  const status = error?.response?.status;

  if (status === 401 || status === 419) {
    return true;
  }

  return status === 403
    && error?.response?.data?.message === INACTIVE_USER_AUTH_MESSAGE;
};

export const getStoredAuthToken = () => localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

export const storeAuthToken = (token) => {
  if (token) {
    localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
  } else {
    localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
  }
};

export const discardLegacyStoredUser = () => {
  localStorage.removeItem(LEGACY_AUTH_USER_STORAGE_KEY);
};

export const clearStoredAuth = () => {
  localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
  discardLegacyStoredUser();
};

export const clearAuthSession = (reason) => {
  clearStoredAuth();

  window.dispatchEvent(new CustomEvent(AUTH_SESSION_CLEARED_EVENT, {
    detail: { reason }
  }));
};
