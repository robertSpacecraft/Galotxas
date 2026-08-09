import { expect, test } from '@playwright/test';

const legalDocuments = [
  {
    path: '/legal/aviso-legal',
    title: 'Aviso legal',
    section: 'Titular del sitio',
  },
  {
    path: '/legal/privacidad',
    title: 'Política de privacidad',
    section: 'Conservación',
  },
  {
    path: '/legal/cookies',
    title: 'Política de cookies y almacenamiento local',
    section: 'Web pública',
  },
];

test.describe('páginas legales versionadas', () => {
  test('carga Legal de forma diferida y abre Aviso legal desde el footer', async ({ page }) => {
    let releaseLegalModule;
    const legalModuleGate = new Promise((resolve) => {
      releaseLegalModule = resolve;
    });

    await page.route('**/src/features/legal/LegalPage.jsx*', async (route) => {
      await legalModuleGate;
      await route.continue();
    });

    await page.goto('/');
    await page.getByRole('contentinfo')
      .getByRole('navigation', { name: 'Información legal' })
      .getByRole('link', { name: 'Aviso legal' })
      .click();

    await expect(page.getByRole('status')).toContainText('Cargando información legal');
    await expect(page.locator('main h1')).toHaveCount(0);
    releaseLegalModule();

    await expect(page).toHaveURL(/\/legal\/aviso-legal$/);
    await expect(page.getByRole('heading', { name: 'Aviso legal', level: 1 })).toBeVisible();
    await expect(page.locator('main h1')).toHaveCount(1);
    await expect(page.getByText('1.0.0')).toBeVisible();
    await expect(page.getByText('06/08/2026')).toBeVisible();
    await expect(page).toHaveTitle('Aviso legal | Club Galotxes Monòver');
  });

  test('publica exactamente los tres documentos y permite navegación interna', async ({ page }) => {
    for (const document of legalDocuments) {
      await page.goto(document.path);
      await expect(page.getByRole('heading', { name: document.title, level: 1 })).toBeVisible();
      await expect(page.getByRole('heading', { name: document.section })).toBeVisible();
      await expect(page.locator('main')
        .getByRole('navigation', { name: 'Información legal' })
        .getByRole('link'))
        .toHaveCount(3);
    }

    const navigation = page.locator('main').getByRole('navigation', { name: 'Información legal' });
    await navigation.getByRole('link', { name: 'Privacidad' }).click();
    await expect(page).toHaveURL(/\/legal\/privacidad$/);
    await expect(navigation.getByRole('link', { name: 'Privacidad' }))
      .toHaveAttribute('aria-current', 'page');

    const aepd = page.getByRole('link', { name: /Agencia Española de Protección de Datos/ });
    await expect(aepd).toHaveAttribute('target', '_blank');
    await expect(aepd).toHaveAttribute('rel', 'noopener noreferrer');
  });

  test('mantiene /legal y descendientes desconocidos en la 404 accesible', async ({ page }) => {
    for (const pathname of ['/legal', '/legal/desconocido', '/legal/privacidad/otra-ruta']) {
      await page.goto(pathname);
      await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeVisible();
      await expect(page).toHaveURL(new RegExp(`${pathname}$`));
    }
  });

  test('no consulta API, no carga terceros automáticos y no muestra banner', async ({ page }) => {
    const apiRequests = [];
    const remoteRequests = [];
    page.on('request', (request) => {
      const url = request.url();
      if (/\/api\/v1\//.test(url)) apiRequests.push(url);
      if (/fonts\.(googleapis|gstatic)|fonts\.bunny|cdn\.jsdelivr/.test(url)) {
        remoteRequests.push(url);
      }
    });

    for (const { path } of legalDocuments) {
      await page.goto(path);
      await expect(page.locator('main')).toBeVisible();
      await expect(page.getByRole('dialog')).toHaveCount(0);
    }

    expect(apiRequests).toEqual([]);
    expect(remoteRequests).toEqual([]);
  });

  test('conserva Contacto oculto y las rutas representativas del MVP', async ({ page }) => {
    await page.route('**/api/v1/contact/config', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: null, data: { enabled: false } }),
    }));

    await page.goto('/club/contacto');
    await expect(page.getByText(/El formulario no está disponible actualmente/)).toBeVisible();
    await expect(page.getByLabel('Nombre', { exact: false })).toHaveCount(0);

    for (const [path, heading] of [
      ['/', 'Galotxas en Monóvar'],
      ['/aprende-a-jugar', 'Aprende a jugar'],
      ['/club/quienes-somos', 'Quiénes somos E2E'],
      ['/login', 'Acceso Jugadores'],
    ]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible();
    }
  });

  test('evita overflow crítico a 320 px con tablas legales desplazables', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 740 });

    for (const path of ['/legal/privacidad', '/legal/cookies']) {
      await page.goto(path);
      await expect.poll(() => page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      )).toBe(true);
      await expect(page.getByRole('contentinfo')).toBeVisible();
    }
  });
});
