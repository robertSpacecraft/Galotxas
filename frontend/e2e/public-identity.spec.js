import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const apiBaseURL = `${backendBaseURL}/api/v1`;
const underFourteenToken = 'a'.repeat(64);
const teenToken = 'b'.repeat(64);
const expiredToken = 'c'.repeat(64);
const deniedToken = 'd'.repeat(64);

const adminCredentials = {
  email: 'admin.e2e@example.test',
  password: 'E2E-password-123!',
};

const json = async (response) => {
  expect(response.ok(), await response.text()).toBe(true);

  return response.json();
};

const fixture = async (page) => {
  const championships = await json(await page.request.get(`${apiBaseURL}/championships`));
  const championship = championships.data.find(({ slug }) => slug === 'identidad-menores-e2e');
  const category = championship.categories.find(({ slug }) => slug === 'identidad-menores-e2e');
  const schedule = await json(
    await page.request.get(`${apiBaseURL}/categories/${category.id}/schedule`),
  );

  return {
    categoryId: category.id,
    underFourteenMatchId: schedule.data
      .find(({ name }) => name === 'Identidad menor de 14 E2E').matches[0].id,
    teenMatchId: schedule.data
      .find(({ name }) => name === 'Identidad 14 a 17 E2E').matches[0].id,
  };
};

const publicMatch = async (page, matchId) => (
  json(await page.request.get(`${apiBaseURL}/matches/${matchId}`))
);

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const openAuthorization = async (page, participantName) => {
  await page.goto(`${backendBaseURL}/admin/public-identity-authorizations`);
  const row = page.getByRole('row').filter({ hasText: participantName });
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Revisar' }).click();
};

const confirmFromPublicPage = async (page, token) => {
  await page.goto(`/public-identity/confirm#token=${token}`);
  await expect(page).toHaveURL(/\/public-identity\/confirm$/);
  await expect(page.getByRole('navigation')).toHaveCount(0);
  await expect(page.getByRole('contentinfo')).toHaveCount(0);
  await page.getByRole('button', { name: 'Confirmar autorización' }).click();
  await expect(page.getByRole('heading', { name: 'Confirmación registrada' })).toBeVisible();
};

test.describe.serial('autorización verificable de identidad pública de menores', () => {
  test('Escuela mantiene separadas privacidad, inscripción e identidad opcional', async ({ page }) => {
    await page.goto('/escuela');
    await page.getByLabel('Nombre completo del participante').fill('Solicitud Alias Fallo Correo E2E');
    await page.getByLabel('Fecha de nacimiento').fill('2014-08-07');
    await page.getByLabel('Teléfono de contacto').fill('600 444 444');
    await page.getByLabel('Correo electrónico de contacto')
      .fill('guardian-mail-failure.e2e@example.test');
    await page.getByLabel('Nombre completo del representante').fill('Representante E2E');
    await page.getByLabel('Relación con el participante').fill('Tutor legal');

    const identityGroup = page.getByRole('radiogroup', { name: 'Modo de identidad pública' });
    await expect(identityGroup.getByRole('radio', { name: /No autorizar identidad/ }))
      .toBeChecked();
    await expect(identityGroup.getByRole('radio', { name: /Autorizar sólo el alias/ }))
      .not.toBeChecked();
    await expect(page.getByText('Aviso NOTICE-PUBLIC-IDENTITY-MINORS, versión 1.0.0.'))
      .toBeVisible();
    await expect(page.getByRole('link', { name: 'Política de privacidad' }).first())
      .toHaveAttribute('href', '/legal/privacidad');

    await identityGroup.getByRole('radio', { name: /Autorizar sólo el alias/ }).check();
    await page.getByLabel(/Declaro que ejerzo la patria potestad o tutela/).check();
    await page.getByLabel('He leído la información de privacidad de la inscripción').check();
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page.getByRole('heading', { name: 'Solicitud recibida' })).toBeVisible();
    await loginAdmin(page);
    await openAuthorization(page, 'Solicitud Alias Fallo Correo E2E');
    await expect(page.getByText('Pendiente', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Notificación fallida')).toBeVisible();
    await expect(page.getByText(/[a-f0-9]{64}/)).toHaveCount(0);

    const withoutAuthorization = await page.request.post(`${apiBaseURL}/school/enrollments`, {
      data: {
        participant_name: 'Inscripción independiente E2E',
        participant_birth_date: '1990-01-01',
        contact_phone: '600 555 555',
        contact_email: 'without-identity.e2e@example.test',
        privacy_acknowledged: true,
        privacy_notice_version: '1.1.0',
      },
    });
    expect(withoutAuthorization.status()).toBe(201);
  });

  test('menor de 14: confirma, se revisa, publica alias y la revocación es inmediata', async ({ page }) => {
    const ids = await fixture(page);
    let match = await publicMatch(page, ids.underFourteenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Participante');

    await confirmFromPublicPage(page, underFourteenToken);
    const reused = await page.request.post(
      `${apiBaseURL}/public-identity/confirmation/confirm`,
      { data: { token: underFourteenToken } },
    );
    expect(reused.status()).toBe(404);

    await loginAdmin(page);
    await openAuthorization(page, 'Menor Alias E2E');
    await expect(page.getByText('Alias deportivo', { exact: true })).toBeVisible();
    await expect(page.getByText('Sin vincular')).toHaveCount(0);
    await page.getByRole('button', { name: 'Aprobar tras revisión' }).click();
    await expect(page.getByText('Autorización aprobada.')).toBeVisible();

    match = await publicMatch(page, ids.underFourteenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Alias Menor E2E');
    expect(JSON.stringify(match)).not.toMatch(
      /guardian|birth_date|authorization|notice_version|private_reason|@example\.test/,
    );

    await page.getByLabel('Motivo privado opcional').fill('Retirada fixture E2E');
    await page.getByRole('button', { name: 'Revocar inmediatamente' }).click();
    await expect(page.getByText('Autorización revocada.')).toBeVisible();
    match = await publicMatch(page, ids.underFourteenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Participante');
  });

  test('menor de 14 a 17 exige conformidad antes de name_initial', async ({ page }) => {
    const ids = await fixture(page);
    let match = await publicMatch(page, ids.teenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Participante');

    await confirmFromPublicPage(page, teenToken);
    await loginAdmin(page);
    await openAuthorization(page, 'Menor Inicial E2E');
    await page.getByRole('button', { name: 'Aprobar tras revisión' }).click();
    await expect(page.getByText(/Debe registrarse la conformidad informada/)).toBeVisible();
    match = await publicMatch(page, ids.teenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Participante');

    await page.getByLabel(/Confirmo que el menor ha sido informado/).check();
    await page.getByRole('button', { name: 'Registrar conformidad' }).click();
    await expect(page.getByText('Conformidad informada del menor registrada.')).toBeVisible();
    await page.getByRole('button', { name: 'Aprobar tras revisión' }).click();
    await expect(page.getByText('Autorización aprobada.')).toBeVisible();

    match = await publicMatch(page, ids.teenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Noa É.');
    expect(match.data.home_entry.public_display_name).not.toContain('Écija');

    await page.getByRole('button', { name: 'Revocar inmediatamente' }).click();
    match = await publicMatch(page, ids.teenMatchId);
    expect(match.data.home_entry.public_display_name).toBe('Participante');
  });

  test('enlaces caducados, denegados e inválidos no permiten enumerar datos', async ({ page }) => {
    await page.goto(`/public-identity/confirm#token=${expiredToken}`);
    await expect(page.getByRole('heading', { name: 'Enlace no disponible' })).toBeVisible();
    await expect(page).toHaveURL(/\/public-identity\/confirm$/);

    await page.goto('/');
    await page.goto(`/public-identity/confirm#token=${deniedToken}`);
    await page.getByRole('button', { name: /Rechazar y mantener/ }).click();
    await expect(page.getByRole('heading', { name: 'Rechazo registrado' })).toBeVisible();
    await page.goto('/');
    await page.goto(`/public-identity/confirm#token=${deniedToken}`);
    await expect(page.getByRole('heading', { name: 'Enlace no disponible' })).toBeVisible();

    await page.goto('/');
    await page.goto(`/public-identity/confirm#token=${'e'.repeat(64)}`);
    await expect(page.getByRole('heading', { name: 'Enlace no disponible' })).toBeVisible();
    await expect(page.getByText(/correo|nacimiento|representante/i)).toHaveCount(0);
  });

  test('confirmación, legales y Manual conservan privacidad, responsive y recursos locales', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    const reloadToken = 'f'.repeat(64);
    const consoleMessages = [];
    const lookupLocations = [];
    page.on('console', (message) => consoleMessages.push(message.text()));
    page.on('request', (request) => {
      if (request.url().includes('/public-identity/confirmation/lookup')) {
        lookupLocations.push(page.url());
      }
    });
    await page.goto('/');
    await page.goto(`/public-identity/confirm#token=${reloadToken}`);
    await expect(page.getByRole('heading', { name: 'Enlace no disponible' })).toBeVisible();
    await expect(page).toHaveURL(/\/public-identity\/confirm$/);
    expect(lookupLocations).toHaveLength(1);
    expect(lookupLocations[0]).not.toContain('#token=');
    expect(JSON.stringify(consoleMessages)).not.toContain(reloadToken);
    expect(await page.evaluate(() => ({
      local: Object.values(localStorage),
      session: Object.values(sessionStorage),
    }))).toEqual({ local: [], session: [] });

    await page.reload();
    await expect(page.getByRole('heading', { name: 'Enlace no disponible' })).toBeVisible();
    expect(lookupLocations).toHaveLength(1);
    await page.goBack();
    await expect(page).toHaveURL(/\/$/);
    expect(page.url()).not.toContain(reloadToken);

    await page.goto(`/public-identity/confirm#token=${reloadToken}`);
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
    await expect(page.locator('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, nofollow, noarchive');
    const externalResources = await page.evaluate(() => performance.getEntriesByType('resource')
      .map(({ name }) => new URL(name).hostname)
      .filter((hostname) => !['web', '127.0.0.1', 'localhost'].includes(hostname)));
    expect(externalResources).toEqual([]);

    await page.goto('/legal/privacidad');
    await expect(page.getByRole('heading', { name: 'Política de privacidad', level: 1 }))
      .toBeVisible();
    await page.goto('/aprende-a-jugar/manual');
    await expect(page.getByRole('heading', { name: 'Manual', level: 1 }))
      .toBeVisible();
    await page.route('**/api/v1/contact/config', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: null, data: { form_enabled: false } }),
      });
    });
    await page.goto('/club/contacto');
    await expect(page.getByRole('button', { name: 'Enviar consulta' })).toHaveCount(0);
  });
});
