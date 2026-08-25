import { expect, test } from '@playwright/test';

const canonicalPages = [
  ['/', '/', 'Galotxas en Monóvar'],
  ['/competicion', '/competicion', 'Competición'],
  ['/noticias', '/noticias', 'Noticias'],
  ['/aprende-a-jugar', '/aprende-a-jugar', 'Aprende a jugar'],
  ['/aprende-a-jugar/manual', '/aprende-a-jugar/manual', 'Manual'],
  [
    '/aprende-a-jugar/manual/reglamento/reglamento',
    '/aprende-a-jugar/manual/reglamento/reglamento',
    'Reglamento',
  ],
  [
    '/aprende-a-jugar/manual/conceptos/juego/bote',
    '/aprende-a-jugar/manual/conceptos/juego/bote',
    'Bote',
  ],
  ['/escuela', '/escuela', 'Escuela de Galotxas'],
  ['/club/quienes-somos', '/club/quienes-somos', 'Quiénes somos E2E'],
  ['/club/contacto', '/club/contacto', 'Contacto E2E'],
  ['/club/federarse', '/club/federarse', 'Federarse E2E'],
  ['/club/documentos', '/club/documentos', 'Documentos E2E'],
  ['/legal/aviso-legal', '/legal/aviso-legal', 'Aviso legal'],
  ['/legal/privacidad', '/legal/privacidad', 'Política de privacidad'],
  ['/legal/cookies', '/legal/cookies', 'Política de cookies y almacenamiento local'],
];

const aliases = [
  ['/nosotros', '/club/quienes-somos'],
  ['/contenidos/nosotros', '/club/quienes-somos'],
  ['/contenidos/contacto', '/club/contacto'],
  ['/contenidos/federarse', '/club/federarse'],
  ['/contenidos/documentos', '/club/documentos'],
];

const noindexFrontendPort = process.env.E2E_NOINDEX_FRONTEND_PORT || '5175';

const expectNoDocumentOverflow = async (page) => {
  await expect.poll(() => page.evaluate(
    () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
  )).toBe(true);
};

test.describe('SEO, indexación y accesibilidad pública', () => {
  test('las rutas canónicas publican un único main, H1 y canonical absoluto', async ({ page }) => {
    for (const [pathname, canonicalPath, heading] of canonicalPages) {
      await page.goto(pathname);
      await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible();
      await expect(page.locator('main')).toHaveCount(1);
      await expect(page.locator('main h1')).toHaveCount(1);
      await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
      await expect(page.locator('link[rel="canonical"]'))
        .toHaveAttribute('href', `https://example.test${canonicalPath}`);
      await expect(page.locator('meta[name="robots"]'))
        .toHaveAttribute('content', 'index, follow');
    }
  });

  test('los aliases institucionales conservan respuesta sin competir en indexación', async ({ page }) => {
    for (const [pathname, canonicalPath] of aliases) {
      await page.goto(pathname);
      await expect(page.locator('main h1')).toHaveCount(1);
      await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
      await expect(page.locator('link[rel="canonical"]'))
        .toHaveAttribute('href', `https://example.test${canonicalPath}`);
      await expect(page.locator('meta[name="robots"]'))
        .toHaveAttribute('content', 'noindex, follow');
    }

    await page.goto('/contenidos/e2e-publicada');
    await expect(page.getByRole('heading', { name: 'Contenido E2E publicado', level: 1 }))
      .toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
    await expect(page.locator('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, follow');
  });

  test('query, hash, slash final y casing no contaminan el canonical', async ({ page }) => {
    await page.goto('/COMPETICION/?vista=lista#temporadas');
    await expect(page.getByRole('heading', { name: 'Competición', level: 1 })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      'href',
      'https://example.test/competicion',
    );
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
  });

  test('Cuenta, token, errores y rutas inexistentes no heredan metadata indexable', async ({ page }) => {
    for (const pathname of [
      '/login',
      '/register',
      '/forgot-password',
      '/reset-password',
      '/player',
    ]) {
      await page.goto(pathname);
      await expect(page.locator('meta[name="robots"]'))
        .toHaveAttribute('content', 'noindex, nofollow');
      await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
    }

    await page.goto('/public-identity/confirm#token=invalid');
    await expect(page.getByRole('heading', { name: 'Identidad pública en competición', level: 1 }))
      .toBeVisible();
    await expect(page.locator('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, nofollow, noarchive');
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);

    for (const pathname of ['/club', '/aprende', '/glosario', '/ruta-desconocida']) {
      await page.goto(pathname);
      await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeVisible();
      await expect(page.locator('meta[name="robots"]'))
        .toHaveAttribute('content', 'noindex, nofollow');
      await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
    }
  });

  test('Home expone OG y SportsClub confirmados sin teléfono ni datos deportivos', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle('Club Galotxes Monòver');
    await expect(page.locator('meta[property="og:type"]')).toHaveAttribute('content', 'website');
    await expect(page.locator('meta[property="og:site_name"]'))
      .toHaveAttribute('content', 'Club Galotxes Monòver');
    await expect(page.locator('meta[property="og:url"]'))
      .toHaveAttribute('content', 'https://example.test/');

    const structuredData = await page.locator('script[data-public-seo-jsonld]').textContent();
    expect(structuredData).toContain('"@type":"SportsClub"');
    expect(structuredData).toContain('"legalName":"Club Galotxes de Monover"');
    expect(structuredData).not.toContain('telephone');
    expect(structuredData).not.toMatch(/(?<!\d)(?:[6789]\d{8})(?!\d)/);

    await page.goto('/rankings');
    await expect(page.getByRole('heading', { name: 'Rankings de Galotxas', level: 1 })).toBeVisible();
    const headText = await page.locator('head').evaluate((head) => [
      head.textContent,
      ...Array.from(head.querySelectorAll('meta'), (meta) => meta.getAttribute('content')),
    ].filter(Boolean).join(' '));
    expect(headText).not.toContain('Pilotari E2E');
    expect(headText).not.toContain('Menor E2E');
    await expect(page).toHaveTitle('Rankings | Club Galotxes Monòver');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta los rankings públicos de Galotxas por histórico, temporada, campeonato y categoría.',
    );
    await expect(page.locator('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, follow');
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
  });

  test('robots y sitemap responden según la configuración de build', async ({ page }) => {
    await page.goto('/');
    const enabledRobots = await page.request.get(new URL('/robots.txt', page.url()).href);
    expect(enabledRobots.ok()).toBe(true);
    expect(await enabledRobots.text()).toBe([
      'User-agent: *',
      'Allow: /',
      'Sitemap: https://example.test/sitemap.xml',
      '',
    ].join('\n'));

    const sitemap = await page.request.get(new URL('/sitemap.xml', page.url()).href);
    expect(sitemap.status()).toBe(200);
    expect(sitemap.headers()['content-type']).toContain('application/xml');
    const sitemapXml = await sitemap.text();
    expect(sitemapXml).toMatch(/^<\?xml version="1\.0" encoding="UTF-8"\?>/);
    expect((sitemapXml.match(/<url>/g) ?? [])).toHaveLength(53);
    expect(sitemapXml).toContain('<loc>https://example.test/legal/privacidad</loc>');
    expect(sitemapXml).toContain(
      '<loc>https://example.test/aprende-a-jugar/manual/reglamento/reglamento</loc>',
    );
    expect(sitemapXml).not.toContain('https://example.test/nosotros</loc>');
    expect(sitemapXml).not.toContain('/login</loc>');
    expect(sitemapXml).not.toContain('/rankings</loc>');

    const disabledBase = `http://127.0.0.1:${noindexFrontendPort}`;
    const disabledRobots = await page.request.get(`${disabledBase}/robots.txt`);
    expect(disabledRobots.ok()).toBe(true);
    expect(await disabledRobots.text()).toBe('User-agent: *\nDisallow: /\n');
    const disabledSitemap = await page.request.get(`${disabledBase}/sitemap.xml`);
    expect(disabledSitemap.status()).toBe(404);
    expect(disabledSitemap.headers()['content-type'] ?? '').not.toContain('text/html');
  });

  test('skip link, foco SPA, announcer y disclosures funcionan con teclado', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('main#main-content')).toBeFocused();

    await page.getByRole('link', { name: 'Ver competición' }).first().click();
    await expect(page.getByRole('heading', { name: 'Competición', level: 1 })).toBeVisible();
    await expect(page.locator('main#main-content')).toBeFocused();
    await expect(page.locator('.route-announcer')).toHaveText('Competición');
    await expect(page.locator('.route-announcer')).toHaveCount(1);

    const navigation = page.getByRole('list', { name: 'Navegación editorial' });
    const learn = navigation.getByRole('button', { name: 'Aprende' });
    const club = navigation.getByRole('button', { name: 'Club' });
    await learn.focus();
    await page.keyboard.press('Enter');
    await expect(learn).toHaveAttribute('aria-expanded', 'true');
    await club.focus();
    await page.keyboard.press('Space');
    await expect(learn).toHaveAttribute('aria-expanded', 'false');
    await expect(club).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(club).toBeFocused();
    await expect(club).toHaveAttribute('aria-expanded', 'false');
  });

  test('formularios, tablas y superficies mantienen reflow sin recursos remotos', async ({ page }) => {
    const remoteRequests = [];
    page.on('request', (request) => {
      const hostname = new URL(request.url()).hostname;
      if (!['127.0.0.1', 'localhost', 'web'].includes(hostname)) remoteRequests.push(request.url());
    });

    await page.goto('/login');
    await expect(page.getByLabel('Correo Electrónico')).toBeVisible();
    await expect(page.getByLabel('Contraseña')).toBeVisible();

    await page.goto('/escuela');
    await expect(page.getByLabel('Nombre', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Teléfono', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Correo electrónico', { exact: false })).toBeVisible();

    await page.route('**/api/v1/contact/config', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: null, data: { enabled: false } }),
    }));
    await page.goto('/club/contacto');
    await expect(page.getByText(/El formulario no está disponible actualmente/)).toBeVisible();
    await expect(page.getByLabel('Correo electrónico', { exact: false })).toHaveCount(0);

    await page.setViewportSize({ width: 320, height: 740 });
    for (const pathname of [
      '/aprende-a-jugar/manual/reglamento/sistema-de-puntuacion',
      '/legal/privacidad',
      '/contenidos/nosotros',
      '/rankings',
    ]) {
      await page.goto(pathname);
      await expect(page.locator('main h1')).toHaveCount(1);
      await expectNoDocumentOverflow(page);
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => {
      document.body.style.zoom = '200%';
    });
    await expectNoDocumentOverflow(page);
    await page.evaluate(() => {
      document.body.style.zoom = '';
    });

    for (const width of [375, 768, 1024, 1280, 1600]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/');
      await expectNoDocumentOverflow(page);
    }

    expect(remoteRequests).toEqual([]);
  });
});
