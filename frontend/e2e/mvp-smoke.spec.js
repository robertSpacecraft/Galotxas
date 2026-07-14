import { expect, test } from '@playwright/test';

const adminBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';

const credentials = {
  admin: {
    email: 'admin.e2e@example.test',
    password: 'E2E-password-123!',
  },
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
  let reviewMatchPath;

  test('navegación pública y página CMS publicada', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'La emoción de las Galotxas' })).toBeVisible();

    await page.goto('/register');
    const playerToggle = page.getByRole('checkbox', { name: 'Soy jugador' });
    await expect(playerToggle).not.toBeChecked();
    await playerToggle.check();
    await expect(page.getByLabel('Apodo (Nickname)')).toBeVisible();
    await expect(page.getByLabel('Nivel de juego (1-10) *')).toBeVisible();

    await page.goto('/forgot-password');
    await expect(page.getByLabel('Correo Electrónico')).toBeVisible();

    await page.goto('/torneos');
    await expect(page.getByRole('heading', { name: 'Torneos', level: 1 })).toBeVisible();
    await expect(page.getByText(/En construcción/)).toHaveCount(0);

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

  test('el CTA principal abre el listado real de torneos', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await page.getByRole('link', { name: 'Ver Torneos' }).click();

    await expect(page).toHaveURL(/\/torneos$/);
    await expect(page.getByRole('heading', { name: 'Torneos' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E' })).toBeVisible();

    assertNoConsoleErrors();
  });

  test('el calendario público muestra todas las jornadas y enlaza sus partidos', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/torneos');
    await page.getByRole('link', { name: 'Ver Torneo' }).click();
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E' })).toBeVisible();
    await page.getByRole('link', { name: 'Ver categoría' }).click();
    await expect(page.getByRole('heading', { name: 'Individual E2E', exact: true })).toBeVisible();

    const categoryPath = new URL(page.url()).pathname;
    await page.goto(`${categoryPath}/standings`);
    await page.getByRole('link', { name: 'Calendario & Resultados' }).click();

    await expect(page).toHaveURL(/\/categories\/\d+\/schedule$/);
    await expect(page.getByRole('heading', { name: 'Individual E2E', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Jornada E2E Confirmación' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Jornada E2E Discrepancia' })).toBeVisible();
    await expect(page.getByText('Pilotari E2E 1')).toHaveCount(2);
    await expect(page.getByText('Pilotari E2E 2')).toHaveCount(2);
    await expect(page.getByText('Pista: Pista E2E')).toHaveCount(2);
    await expect(page.getByText('No hay jornadas configuradas todavía.')).toHaveCount(0);

    await page.getByRole('link', { name: 'Ver partido: Pilotari E2E 1 contra Pilotari E2E 2' }).first().click();
    await expect(page).toHaveURL(/\/matches\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Detalles de la partida' })).toBeVisible();
    await expect(page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2')).toBeVisible();

    assertNoConsoleErrors();
  });

  test('la navegación móvil permite recorrer enlaces públicos sin desbordamiento', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/');
    const openMenu = page.getByRole('button', { name: 'Abrir menú de navegación' });
    await expect(openMenu).toBeVisible();
    await expect(openMenu).toHaveAttribute('aria-expanded', 'false');

    await openMenu.click();
    const closeMenu = page.getByRole('button', { name: 'Cerrar menú de navegación' });
    await expect(closeMenu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('link', { name: 'Inicio', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Torneos', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Rankings', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Contenidos', exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'Rankings', exact: true }).click();
    await expect(page).toHaveURL(/\/rankings$/);
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');

    await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
    await page.getByRole('link', { name: 'Contenidos', exact: true }).click();
    await expect(page).toHaveURL(/\/contenidos$/);
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');

    const hasNoHorizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    );
    expect(hasNoHorizontalOverflow).toBe(true);

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
    reviewMatchPath = new URL(page.url()).pathname;
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

  test('el administrador resuelve la discrepancia desde el panel Blade', async ({ page }) => {
    await page.goto(`${adminBaseURL}/admin/login`);
    await page.getByLabel('Email').fill(credentials.admin.email);
    await page.getByLabel('Contraseña').fill(credentials.admin.password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    const adminMenuToggle = page.getByRole('button', { name: 'Abrir menú de administración' });
    await expect(adminMenuToggle).toHaveAttribute('aria-expanded', 'false');
    await adminMenuToggle.click();
    await expect(adminMenuToggle).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('link', { name: 'Conflictos', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Conflictos de resultados' })).toBeVisible();
    await expect(page.getByRole('cell', { name: '10 - 6' })).toBeVisible();
    await expect(page.getByRole('cell', { name: '7 - 10' })).toBeVisible();
    await page.getByRole('link', { name: 'Revisar y resolver' }).click();

    await expect(page.getByRole('heading', { name: 'Resolver conflicto de resultado' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Reporte local' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Reporte visitante' })).toBeVisible();
    await page.getByLabel('Tanteo oficial local').fill('10');
    await page.getByLabel('Tanteo oficial visitante').fill('8');

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar resolución' }).click();

    await expect(page).toHaveURL(/\/admin\/match-conflicts$/);
    await expect(page.getByRole('alert')).toContainText('Conflicto resuelto y resultado validado correctamente.');
    await expect(page.getByText('No hay conflictos de resultados pendientes de resolución.')).toBeVisible();

    await page.goto(reviewMatchPath);
    await expect(page.getByText('Finalizado', { exact: true })).toBeVisible();
    const officialScore = page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2');
    await expect(officialScore).toContainText('10');
    await expect(officialScore).toContainText('8');
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
