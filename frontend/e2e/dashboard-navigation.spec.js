import { expect, test } from '@playwright/test';

const tabLabels = [
  'Resumen',
  'Mis Inscripciones',
  'Mis Partidos',
  'Calendario',
  'Rankings',
];

const playerUser = {
  id: 9001,
  name: 'Auditoría',
  lastname: 'Móvil',
  email: 'audit@example.test',
  role: 'user',
  profile_photo: null,
  player: {
    id: 9101,
    nickname: 'Audit',
    dni: null,
    gender: 'other',
    level: 2,
    dominant_hand: 'right',
    license_number: 'AUDIT-1',
    notes: null,
  },
};

const mockPrivateApi = async (page, privateRequests) => {
  await page.addInitScript(() => {
    localStorage.setItem('token', 'dashboard-navigation-e2e-token');
  });

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request();
    const pathname = new URL(request.url()).pathname;

    if (pathname.startsWith('/api/v1/me/')) {
      privateRequests.push(pathname);
    }

    const data = pathname === '/api/v1/me' ? { user: playerUser } : [];

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: null, data }),
    });
  });
};

const readTabsLayout = async (tablist) => tablist.evaluate((container) => {
  const containerRect = container.getBoundingClientRect();
  const tabs = [...container.querySelectorAll('[role="tab"]')];
  const selectedTab = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true');
  const selectedRect = selectedTab?.getBoundingClientRect();
  const rowTops = new Set(tabs.map((tab) => Math.round(tab.getBoundingClientRect().top)));

  return {
    allInside: tabs.every((tab) => {
      const rect = tab.getBoundingClientRect();
      return rect.left >= containerRect.left - 0.5
        && rect.right <= containerRect.right + 0.5;
    }),
    noOverflow: container.scrollWidth <= container.clientWidth,
    rows: rowTops.size,
    selectedInside: Boolean(selectedRect)
      && selectedRect.left >= containerRect.left - 0.5
      && selectedRect.right <= containerRect.right + 0.5,
  };
});

test('Mi Panel mantiene sus cinco secciones visibles, accesibles y funcionales', async ({ page }) => {
  const consoleErrors = [];
  const privateRequests = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await mockPrivateApi(page, privateRequests);
  await page.goto('/player');

  await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
  const tablist = page.getByRole('tablist', { name: 'Secciones de Mi Panel' });
  await expect(tablist).toBeVisible();
  await expect(tablist.getByRole('tab')).toHaveCount(5);

  const viewportMatrix = [
    { width: 320, rows: 3 },
    { width: 360, rows: 3 },
    { width: 390, rows: 3 },
    { width: 430, rows: 3 },
    { width: 600, rows: 2 },
    { width: 768, rows: 2 },
    { width: 1024, rows: 1 },
    { width: 1440, rows: 1 },
  ];

  for (const [index, viewport] of viewportMatrix.entries()) {
    await page.setViewportSize({ width: viewport.width, height: 1000 });
    const tab = tablist.getByRole('tab', { name: tabLabels[index % tabLabels.length] });
    await tab.click();
    await expect(tab).toHaveAttribute('aria-selected', 'true');

    await expect.poll(() => readTabsLayout(tablist)).toEqual({
      allInside: true,
      noOverflow: true,
      rows: viewport.rows,
      selectedInside: true,
    });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
  }

  await page.setViewportSize({ width: 390, height: 900 });
  await tablist.getByRole('tab', { name: 'Resumen' }).click();
  await expect(page.getByRole('heading', { name: 'Datos de Usuario' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Perfil de Jugador' })).toBeVisible();
  await expect(page.getByRole('region', { name: 'Foto de perfil' })).toBeVisible();

  await tablist.getByRole('tab', { name: 'Mis Inscripciones' }).click();
  await expect(page.getByText('Aún no te has inscrito a ningún torneo.')).toBeVisible();
  await tablist.getByRole('tab', { name: 'Mis Partidos' }).click();
  await expect(page.getByText('No tienes partidos registrados todavía.')).toBeVisible();
  await tablist.getByRole('tab', { name: 'Calendario' }).click();
  await expect(page.getByText('No tienes eventos próximos en el calendario.')).toBeVisible();
  await tablist.getByRole('tab', { name: 'Rankings' }).click();
  await expect(page.getByText(/No tienes datos de ranking registrados/)).toBeVisible();

  privateRequests.length = 0;
  const summaryTab = tablist.getByRole('tab', { name: 'Resumen' });
  const registrationsTab = tablist.getByRole('tab', { name: 'Mis Inscripciones' });
  const rankingsTab = tablist.getByRole('tab', { name: 'Rankings' });
  await summaryTab.click();
  await summaryTab.focus();

  await page.keyboard.press('ArrowRight');
  await expect(registrationsTab).toBeFocused();
  await expect(summaryTab).toHaveAttribute('aria-selected', 'true');
  expect(privateRequests).toEqual([]);
  expect(await registrationsTab.evaluate((tab) => ({
    focusVisible: tab.matches(':focus-visible'),
    outlineStyle: getComputedStyle(tab).outlineStyle,
  }))).toEqual({ focusVisible: true, outlineStyle: 'solid' });

  await page.keyboard.press('ArrowLeft');
  await page.keyboard.press('ArrowLeft');
  await expect(rankingsTab).toBeFocused();
  await expect(summaryTab).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('Home');
  await expect(summaryTab).toBeFocused();
  await page.keyboard.press('End');
  await expect(rankingsTab).toBeFocused();
  await expect(summaryTab).toHaveAttribute('aria-selected', 'true');
  expect(privateRequests).toEqual([]);

  await page.keyboard.press('Enter');
  await expect(rankingsTab).toHaveAttribute('aria-selected', 'true');
  await expect.poll(() => privateRequests.includes('/api/v1/me/rankings')).toBe(true);

  await page.keyboard.press('Home');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  const calendarTab = tablist.getByRole('tab', { name: 'Calendario' });
  await expect(calendarTab).toBeFocused();
  await page.keyboard.press('Space');
  await expect(calendarTab).toHaveAttribute('aria-selected', 'true');

  await page.setViewportSize({ width: 1024, height: 900 });
  await rankingsTab.click();
  await page.setViewportSize({ width: 320, height: 900 });
  await expect.poll(() => readTabsLayout(tablist)).toEqual({
    allInside: true,
    noOverflow: true,
    rows: 3,
    selectedInside: true,
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.evaluate(() => {
    document.body.style.zoom = '200%';
  });
  await expect.poll(() => readTabsLayout(tablist)).toMatchObject({
    allInside: true,
    noOverflow: true,
    selectedInside: true,
  });
  await expect.poll(() => page.evaluate(
    () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
  )).toBe(true);
  await page.evaluate(() => {
    document.body.style.zoom = '';
  });

  expect(consoleErrors).toEqual([]);
});
