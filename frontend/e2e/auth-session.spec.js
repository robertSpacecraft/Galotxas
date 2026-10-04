import { expect, test } from '@playwright/test';
import {
  collectConsoleErrors,
  createSessionApiClient,
  expectHttpOnlySessionCookie,
  expectNoStoredAuth,
  sessionRequestHeaders,
  spaSessionCookie,
} from './support/sessionAuth.js';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const PASSWORD = 'E2E-password-123!';
const player1 = { email: 'player1.e2e@example.test', password: PASSWORD };
const player2 = { email: 'player2.e2e@example.test', password: PASSWORD };
const admin = { email: 'admin.e2e@example.test', password: PASSWORD };

const accountGroup = (page) => page.getByRole('group', { name: 'Cuenta' });
const signInLink = (page) => accountGroup(page).getByRole('link', { name: 'Iniciar sesión' });
const panelLink = (page) => accountGroup(page).getByRole('link', { name: 'Mi Panel' });

const uiLogin = async (page, credentials) => {
  await page.goto('/login');
  await page.getByLabel('Correo Electrónico').fill(credentials.email);
  await page.getByLabel('Contraseña').fill(credentials.password);
  await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
  await expect(page).toHaveURL(/\/player$/);
  await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
};

const uiLogout = async (page) => {
  await page.getByRole('button', { name: 'Salir' }).click();
  await expect(signInLink(page)).toBeVisible();
};

test.describe('sesión SPA de cookie HttpOnly', () => {
  test('el arranque anónimo consulta /me sin crear sesión ni pedir CSRF', async ({ page }) => {
    const errors = [];
    collectConsoleErrors(page, errors);
    const apiCalls = [];
    page.on('request', (request) => {
      const { pathname } = new URL(request.url());
      if (pathname.startsWith('/api/v1/')) apiCalls.push({ pathname, mode: request.headers()['x-galotxas-auth-mode'] });
    });

    await page.goto('/');
    await expect(signInLink(page)).toBeVisible();

    expect(apiCalls.find((call) => call.pathname === '/api/v1/me')?.mode).toBe('session');
    expect(apiCalls.some((call) => call.pathname === '/api/v1/auth/csrf')).toBe(false);
    expect(await spaSessionCookie(page)).toBeUndefined();
    await expectNoStoredAuth(page);
    expect(errors).toEqual([]);
  });

  test('el contenido público se pinta sin esperar a /me y la cuenta se resuelve después', async ({ page }) => {
    let releaseMe;
    const meGate = new Promise((resolve) => {
      releaseMe = resolve;
    });
    await page.route('**/api/v1/me', async (route) => {
      await meGate;
      await route.continue();
    });

    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Galotxes en Monóvar' })).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Saltar al contenido principal' })).toBeFocused();
    await expect(signInLink(page)).toHaveCount(0);

    releaseMe();

    await expect(signInLink(page)).toBeVisible();
    expect(await spaSessionCookie(page)).toBeUndefined();
  });

  test('/login con sesión válida no muestra el formulario y redirige a /player', async ({ page }) => {
    await uiLogin(page, player1);
    let releaseMe;
    const meGate = new Promise((resolve) => {
      releaseMe = resolve;
    });
    await page.route('**/api/v1/me', async (route) => {
      await meGate;
      await route.continue();
    });

    await page.goto('/login');

    await expect(page.getByRole('status')).toContainText('Comprobando tu sesión');
    await expect(page.getByLabel('Correo Electrónico')).toHaveCount(0);
    releaseMe();
    await expect(page).toHaveURL(/\/player$/);
  });

  test('login real: sin credenciales en el almacenamiento, cookie HttpOnly y recarga autenticada', async ({ page }) => {
    const requests = [];
    page.on('request', (request) => {
      const { pathname } = new URL(request.url());
      if (pathname.startsWith('/api/v1/')) requests.push({ pathname, method: request.method(), headers: request.headers() });
    });

    await uiLogin(page, player1);

    await expectNoStoredAuth(page);
    await expectHttpOnlySessionCookie(page);
    expect(requests.every((request) => request.headers.authorization === undefined)).toBe(true);
    const login = requests.find((request) => request.pathname === '/api/v1/auth/session/login');
    expect(login.headers['x-csrf-token']).toBeTruthy();
    expect(login.headers['x-galotxas-auth-mode']).toBe('session');

    await page.reload();
    await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
    await expect(panelLink(page)).toBeVisible();
    await expectNoStoredAuth(page);

    await page.goto('/player');
    await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
  });

  test('logout con CSRF, /player redirige a login y la cookie ya no autentica', async ({ page }) => {
    await uiLogin(page, player1);
    const logoutRequest = page.waitForRequest((request) => (
      request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/auth/session/logout'
    ));

    await uiLogout(page);

    expect((await logoutRequest).headers()['x-csrf-token']).toBeTruthy();
    await expectNoStoredAuth(page);
    await page.goto('/player');
    await expect(page).toHaveURL(/\/login$/);

    const me = await page.request.get(`${backendBaseURL}/api/v1/me`, { headers: sessionRequestHeaders(page), failOnStatusCode: false });
    expect(me.status()).toBe(401);
  });

  test('registro con perfil de jugador: la sesión nace de la cookie y sobrevive a la recarga completa', async ({ page }) => {
    const suffix = Date.now();
    const email = `registro.${suffix}@example.test`;
    const mutations = [];
    page.on('request', (request) => {
      const { pathname } = new URL(request.url());
      if (request.method() === 'POST' && pathname.startsWith('/api/v1/')) {
        mutations.push({ pathname, headers: request.headers() });
      }
    });

    await page.goto('/register');
    await page.getByLabel('Nombre *').fill('Registro');
    await page.getByLabel('Apellidos *').fill('Sesión E2E');
    await page.getByLabel('Correo Electrónico *').fill(email);
    await page.getByLabel('Confirmar Correo *').fill(email);
    await page.getByLabel(/^Contraseña \*/).fill(PASSWORD);
    await page.getByLabel('Confirmar Contraseña *').fill(PASSWORD);
    await page.getByRole('checkbox', { name: 'Soy jugador' }).check();
    await page.getByLabel('Apodo (Nickname)').fill(`Alias ${suffix}`);
    await page.getByLabel('Fecha de Nacimiento *').fill('1990-05-05');
    await page.getByLabel('Confirmo que la fecha de nacimiento indicada es correcta.').check();
    await page.getByLabel(/He leído la/).check();
    await page.getByRole('button', { name: 'Registrarse' }).click();

    await expect(page).toHaveURL(/\/player$/);
    await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
    await expect(page.getByText(email)).toBeVisible();
    await expectNoStoredAuth(page);
    await expectHttpOnlySessionCookie(page);

    const paths = mutations.map((mutation) => mutation.pathname);
    expect(paths).toEqual(['/api/v1/auth/session/register', '/api/v1/me/player-profile']);
    for (const mutation of mutations) {
      expect(mutation.headers['x-csrf-token']).toBeTruthy();
      expect(mutation.headers.authorization).toBeUndefined();
    }

    await page.reload();
    await expect(page.getByText(email)).toBeVisible();
  });

  test('un 403 de inactividad invalida la sesión local', async ({ page }) => {
    await uiLogin(page, player1);
    await page.route('**/api/v1/me/rankings', (route) => route.fulfill({
      status: 403,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'El usuario está inactivo.', data: null }),
    }));

    await page.getByRole('tab', { name: 'Rankings' }).click();

    await expect(signInLink(page)).toBeVisible();
    await page.unroute('**/api/v1/me/rankings');
    await expectNoStoredAuth(page);
  });

  test('un 419 en una mutación refresca el CSRF y repite la petición una vez', async ({ page }) => {
    await uiLogin(page, player1);
    let logoutAttempts = 0;
    let csrfRefreshes = 0;
    const logoutTokens = [];
    page.on('request', (request) => {
      if (new URL(request.url()).pathname === '/api/v1/auth/csrf') csrfRefreshes += 1;
    });
    await page.route('**/api/v1/auth/session/logout', async (route) => {
      logoutAttempts += 1;
      logoutTokens.push(route.request().headers()['x-csrf-token']);
      if (logoutAttempts === 1) {
        await route.fulfill({
          status: 419,
          contentType: 'application/json',
          body: JSON.stringify({ message: 'CSRF token mismatch.', data: null }),
        });

        return;
      }
      await route.continue();
    });

    await uiLogout(page);

    expect(logoutAttempts).toBe(2);
    expect(csrfRefreshes).toBe(1);
    expect(logoutTokens.every(Boolean)).toBe(true);
  });

  test('dos pestañas: el cierre de sesión y el cambio de cuenta se sincronizan', async ({ context }) => {
    const tabA = await context.newPage();
    const tabB = await context.newPage();
    await tabB.goto('/');
    await expect(signInLink(tabB)).toBeVisible();

    await uiLogin(tabA, player1);
    await expect(panelLink(tabB)).toBeVisible();

    await tabB.goto('/player');
    await expect(tabB.getByText(player1.email)).toBeVisible();

    await uiLogout(tabA);
    await expect(signInLink(tabB)).toBeVisible();

    await uiLogin(tabA, player2);
    await tabB.goto('/');
    await expect(panelLink(tabB)).toBeVisible();
    await tabB.getByRole('link', { name: 'Mi Panel' }).click();
    await expect(tabB.getByText(player2.email)).toBeVisible();
    await expect(tabB.getByText(player1.email)).toHaveCount(0);

    await uiLogout(tabB);
    await expect(signInLink(tabA)).toBeVisible();
  });

  test('una API de administración responde a la cookie del administrador', async ({ page }) => {
    await uiLogin(page, admin);

    const response = await page.request.get(`${backendBaseURL}/api/v1/admin/seasons`, { headers: sessionRequestHeaders(page) });

    expect(response.status()).toBe(200);
    await expectNoStoredAuth(page);
  });

  test('la inscripción de Escuela autenticada usa la sesión y la anónima sigue sin sesión', async ({ page }) => {
    const enrollments = [];
    page.on('request', (request) => {
      if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/school/enrollments') {
        enrollments.push(request.headers());
      }
    });
    const fill = async (suffix) => {
      await page.getByLabel('Nombre completo del participante').fill(`Persona ${suffix}`);
      await page.getByLabel('Fecha de nacimiento').fill('1990-01-01');
      await page.getByLabel('Nivel solicitado (opcional)').selectOption({ label: 'Adultos E2E' });
      await page.getByLabel('Teléfono de contacto').fill('611 000 000');
      await page.getByLabel('Correo electrónico de contacto').fill(`${suffix}@example.test`);
      await page.getByLabel('He leído la información de privacidad de la inscripción').check();
      await page.getByRole('button', { name: 'Enviar solicitud' }).click();
      await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
    };

    await page.goto('/escuela');
    await fill('anonima');
    expect(enrollments[0]['x-galotxas-auth-mode']).toBeUndefined();
    expect(enrollments[0]['x-csrf-token']).toBeUndefined();
    expect(await spaSessionCookie(page)).toBeUndefined();

    await uiLogin(page, player1);
    await page.goto('/escuela');
    await fill('autenticada');
    expect(enrollments[1]['x-galotxas-auth-mode']).toBe('session');
    expect(enrollments[1]['x-csrf-token']).toBeTruthy();
    expect(enrollments[1].authorization).toBeUndefined();
  });

  test('el cliente de sesión para preparar datos administra sin Bearer y exige CSRF en las mutaciones', async ({ request, baseURL }) => {
    const origin = new URL(baseURL).origin;
    const session = await createSessionApiClient(request, {
      apiBaseURL: `${backendBaseURL}/api/v1`,
      origin,
      email: admin.email,
      password: admin.password,
    });

    expect(session.headers('GET').Authorization).toBeUndefined();
    expect(session.headers('GET')['X-CSRF-TOKEN']).toBeUndefined();
    expect(session.headers('PATCH')['X-CSRF-TOKEN']).toBeTruthy();

    const seasons = await request.get(`${backendBaseURL}/api/v1/admin/seasons`, { headers: session.headers('GET') });
    expect(seasons.status()).toBe(200);

    const withoutCsrf = await request.post(`${backendBaseURL}/api/v1/admin/seasons`, {
      headers: session.headers('GET'),
      data: {},
      failOnStatusCode: false,
    });
    expect(withoutCsrf.status()).toBe(419);

    const withCsrf = await request.post(`${backendBaseURL}/api/v1/admin/seasons`, {
      headers: session.headers('POST'),
      data: {},
      failOnStatusCode: false,
    });
    expect(withCsrf.status()).toBe(422);
  });
});
