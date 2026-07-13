import { expect, test } from '@playwright/test';

const credentials = {
  player1: {
    email: 'player1.e2e@example.test',
    password: 'E2E-password-123!',
  },
  player2: {
    email: 'player2.e2e@example.test',
    password: 'E2E-password-123!',
  },
};

const watchCriticalConsoleErrors = (page) => {
  const errors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });

  return () => expect(errors, `Errores críticos de consola: ${errors.join('\n')}`).toEqual([]);
};

const login = async (page, user) => {
  await page.goto('/login');
  await page.getByLabel('Correo Electrónico').fill(user.email);
  await page.getByLabel('Contraseña').fill(user.password);
  await page.getByRole('button', { name: 'Iniciar Sesión' }).click();

  await expect(page).toHaveURL(/\/player$/);
  await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
};

const logout = async (page) => {
  await page.getByRole('button', { name: 'Salir' }).click();
  await expect(page.getByRole('link', { name: 'Área de jugadores' })).toBeVisible();
};

const fillScore = async (page, homeScore, awayScore) => {
  await page.getByRole('spinbutton', { name: 'Pilotari E2E 1' }).fill(String(homeScore));
  await page.getByRole('spinbutton', { name: 'Pilotari E2E 2' }).fill(String(awayScore));
};

test.describe.serial('smoke narrativo del MVP', () => {
  let confirmationMatchPath;

  test('navegación pública y página CMS publicada', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'La emoción de las Galotxas' })).toBeVisible();

    await page.getByRole('link', { name: 'Contenidos', exact: true }).click();
    await expect(page).toHaveURL(/\/contenidos$/);
    await expect(page.getByRole('heading', { name: 'Contenidos' })).toBeVisible();

    const publishedPage = page.getByRole('article').filter({ hasText: 'Contenido E2E publicado' });
    await expect(publishedPage).toBeVisible();
    await publishedPage.getByRole('link', { name: 'Ver contenido' }).click();

    await expect(page).toHaveURL(/\/contenidos\/e2e-publicada$/);
    await expect(page.getByRole('heading', { name: 'Contenido E2E publicado' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Escenario público E2E' })).toBeVisible();
    await expect(page.getByText('Este contenido procede exclusivamente de la base temporal E2E.')).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('login real, Mi Panel y acceso a una acción pendiente', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await expect(page.getByRole('heading', { name: 'Datos de Usuario' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Perfil de Jugador' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Acciones pendientes' })).toBeVisible();
    await expect(page.getByLabel('2 acciones pendientes')).toBeVisible();

    await page.getByRole('link', { name: 'Enviar resultado' }).first().click();
    await expect(page).toHaveURL(/\/matches\/\d+$/);
    confirmationMatchPath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Gestión del resultado' })).toBeVisible();

    assertNoConsoleErrors();
  });

  test('resultado coincidente, validación oficial y desaparición de la acción', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await page.goto(confirmationMatchPath);
    await expect(page.getByRole('heading', { name: 'Enviar resultado' })).toBeVisible();
    await fillScore(page, 10, 7);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();

    await logout(page);
    await login(page, credentials.player2);
    await page.getByRole('link', { name: 'Confirmar resultado' }).click();
    await expect(page).toHaveURL(confirmationMatchPath);
    await page.getByRole('button', { name: 'Confirmar este resultado' }).click();

    await expect(page.getByText('Resultado validado oficialmente.')).toBeVisible();
    const officialScore = page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2');
    await expect(officialScore).toContainText('10');
    await expect(officialScore).toContainText('7');

    await page.goto('/player');
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Confirmar resultado' })).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('resultado discrepante queda en revisión sin formulario editable', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await page.getByRole('link', { name: 'Enviar resultado' }).click();
    await fillScore(page, 10, 6);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();

    await logout(page);
    await login(page, credentials.player2);
    await page.getByRole('link', { name: 'Confirmar resultado' }).click();
    await expect(page.getByRole('heading', { name: 'Reportar una discrepancia' })).toBeVisible();
    await fillScore(page, 7, 10);
    await page.getByRole('button', { name: 'Enviar discrepancia' }).click();

    await expect(page.getByText('Estado del flujo: En revisión')).toBeVisible();
    await expect(page.getByText(/Hay una discrepancia entre reportes/)).toBeVisible();

    await page.goto('/player');
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await expect(page.getByText('Resultado en revisión')).toBeVisible();
    await page.getByRole('link', { name: 'Ver revisión' }).click();
    await expect(page.getByText(/Hay una discrepancia entre reportes/)).toBeVisible();
    await expect(page.getByRole('spinbutton')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Enviar resultado|Enviar discrepancia|Confirmar este resultado/ })).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('el ranking refleja el resultado validado sin escala incorrecta ni NaN', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/rankings');
    await expect(page.getByRole('heading', { name: 'Rankings Galotxas' })).toBeVisible();

    const winnerRow = page.getByRole('row').filter({ hasText: 'Pilotari E2E 1' });
    await expect(winnerRow).toBeVisible();
    await expect(winnerRow).toContainText('100,0%');
    await expect(page.getByText('NaN', { exact: true })).toHaveCount(0);

    assertNoConsoleErrors();
  });
});
