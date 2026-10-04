import { expect } from '@playwright/test';

export const SPA_SESSION_COOKIE = 'galotxas-spa-session';

// Un arranque anónimo consulta /me y el navegador registra el 401 esperado.
export const isAnonymousSessionProbe = (message) => {
  const url = message.location()?.url ?? '';

  return /status of 401/.test(message.text()) && /\/api\/v1\/me$/.test(url);
};

export const collectConsoleErrors = (page, errors) => {
  page.on('console', (message) => {
    if (message.type() === 'error' && !isAnonymousSessionProbe(message)) {
      errors.push(message.text());
    }
  });
};

export const readBrowserStorage = (page) => page.evaluate(() => ({
  local: Object.fromEntries(Object.entries(localStorage)),
  session: Object.fromEntries(Object.entries(sessionStorage)),
}));

export const expectNoStoredAuth = async (page) => {
  await expect.poll(() => readBrowserStorage(page)).toEqual({ local: {}, session: {} });
};

export const spaSessionCookie = async (page) => (
  (await page.context().cookies()).find((cookie) => cookie.name === SPA_SESSION_COOKIE)
);

export const expectHttpOnlySessionCookie = async (page) => {
  await expect.poll(async () => {
    const cookie = await spaSessionCookie(page);

    return cookie ? { httpOnly: cookie.httpOnly, sameSite: cookie.sameSite } : null;
  }).toEqual({ httpOnly: true, sameSite: 'Lax' });
  await expect.poll(() => page.evaluate(() => document.cookie)).not.toContain(SPA_SESSION_COOKIE);
};

// Cabeceras de una petición directa de la SPA (el contexto comparte la cookie del navegador).
export const sessionRequestHeaders = (page) => ({
  Accept: 'application/json',
  Origin: new URL(page.url()).origin,
  'X-Galotxas-Auth-Mode': 'session',
});

// Cliente de API por sesión SPA para preparar/limpiar datos desde un APIRequestContext sin navegador.
// Reutiliza el contrato de J3: CSRF por JSON, login de sesión, cookie en el contexto y
// X-CSRF-TOKEN sólo en las mutaciones. Nunca usa Authorization ni emite un PAT.
export const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

export const createSessionApiClient = async (request, {
  apiBaseURL,
  origin,
  email,
  password,
}) => {
  const base = {
    Accept: 'application/json',
    Origin: origin,
    'X-Galotxas-Auth-Mode': 'session',
  };

  const csrfResponse = await request.get(`${apiBaseURL}/auth/csrf`, { headers: base });
  expect(csrfResponse.ok()).toBe(true);
  const bootstrapToken = (await csrfResponse.json()).data.csrf_token;

  const loginResponse = await request.post(`${apiBaseURL}/auth/session/login`, {
    headers: { ...base, 'X-CSRF-TOKEN': bootstrapToken },
    data: { email, password },
  });
  expect(loginResponse.ok()).toBe(true);
  const loginBody = (await loginResponse.json()).data;
  expect(loginBody.token).toBeUndefined();

  const csrfToken = loginBody.csrf_token;

  return {
    headers: (method = 'GET') => (
      MUTATING_METHODS.includes(method.toUpperCase())
        ? { ...base, 'X-CSRF-TOKEN': csrfToken }
        : { ...base }
    ),
  };
};
