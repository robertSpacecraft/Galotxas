export const AUTH_SESSION_CLEARED_EVENT = 'galotxas:auth-session-cleared';
export const AUTH_MODE_HEADER = 'X-Galotxas-Auth-Mode';
export const AUTH_MODE_SESSION = 'session';
export const CSRF_HEADER = 'X-CSRF-TOKEN';
export const AUTH_CHANNEL_NAME = 'galotxas-auth';
export const AUTH_EVENT_SESSION_CHANGED = 'session-changed';
export const AUTH_EVENT_SESSION_ENDED = 'session-ended';
export const INACTIVE_USER_AUTH_MESSAGE = 'El usuario está inactivo.';

// Claves del contrato Bearer anterior. Sólo se usan para borrar almacenamiento obsoleto.
const LEGACY_TOKEN_STORAGE_KEY = 'token';
const LEGACY_USER_STORAGE_KEY = 'user';

const SAFE_METHODS = new Set(['get', 'head', 'options']);

// Estado de sesión sólo en memoria: ningún valor de este módulo se persiste.
let csrfToken = null;
let sessionExpected = false;

export const isSafeMethod = (method) => SAFE_METHODS.has(String(method || 'get').toLowerCase());

export const getCsrfToken = () => csrfToken;

export const setCsrfToken = (token) => {
  csrfToken = typeof token === 'string' && token !== '' ? token : null;
};

export const clearCsrfToken = () => {
  csrfToken = null;
};

export const isSessionExpected = () => sessionExpected;

export const setSessionExpected = (expected) => {
  sessionExpected = expected === true;
};

/** Olvida el estado de sesión de esta pestaña sin avisar a nadie. */
export const resetSessionState = () => {
  clearCsrfToken();
  setSessionExpected(false);
};

export const shouldInvalidateAuthSession = (error) => {
  const status = error?.response?.status;

  if (status === 401 || status === 419) {
    return true;
  }

  return status === 403
    && error?.response?.data?.message === INACTIVE_USER_AUTH_MESSAGE;
};

// --- Sincronización entre pestañas (sin credenciales ni datos personales) ---

const TAB_ID = (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
  ? crypto.randomUUID()
  : `tab-${Math.random().toString(36).slice(2)}`;

const openChannel = () => {
  if (typeof BroadcastChannel === 'undefined') {
    return null;
  }

  try {
    return new BroadcastChannel(AUTH_CHANNEL_NAME);
  } catch {
    return null;
  }
};

export const broadcastAuthEvent = (type) => {
  const channel = openChannel();

  try {
    channel?.postMessage({ type, source: TAB_ID });
  } catch {
    // Sincronización best-effort: la siguiente petición revelará el estado real.
  } finally {
    channel?.close();
  }
};

/** Escucha eventos de otras pestañas. Devuelve la función que cierra el canal. */
export const subscribeAuthEvents = (handler) => {
  const channel = openChannel();

  if (!channel) {
    return () => {};
  }

  channel.onmessage = (event) => {
    const message = event?.data;

    if (!message || message.source === TAB_ID) {
      return;
    }

    if (message.type === AUTH_EVENT_SESSION_CHANGED || message.type === AUTH_EVENT_SESSION_ENDED) {
      handler(message.type);
    }
  };

  return () => {
    channel.onmessage = null;
    channel.close();
  };
};

/** Cierra la sesión local por invalidación observada, avisa a la app y a otras pestañas. */
export const clearAuthSession = (reason) => {
  resetSessionState();

  window.dispatchEvent(new CustomEvent(AUTH_SESSION_CLEARED_EVENT, {
    detail: { reason }
  }));
  broadcastAuthEvent(AUTH_EVENT_SESSION_ENDED);
};

// --- Migración desde el contrato Bearer anterior ---

/**
 * Borra el almacenamiento local heredado cuando vuelve un navegador antiguo.
 * Nunca lee el valor, lo reutiliza ni lo transmite: sólo elimina las claves.
 * Tolera que localStorage no esté disponible.
 */
export const cleanupLegacyAuthStorage = () => {
  try {
    localStorage.removeItem(LEGACY_TOKEN_STORAGE_KEY);
    localStorage.removeItem(LEGACY_USER_STORAGE_KEY);
  } catch {
    // Sin almacenamiento accesible no queda nada que borrar.
  }
};
