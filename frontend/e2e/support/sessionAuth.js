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
