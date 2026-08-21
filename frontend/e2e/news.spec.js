import { deflateSync } from 'node:zlib';
import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const adminCredentials = {
  email: 'admin.e2e@example.test',
  password: 'E2E-password-123!',
};

const crcTable = Array.from({ length: 256 }, (_, value) => {
  let crc = value;
  for (let bit = 0; bit < 8; bit += 1) {
    crc = (crc & 1) === 1 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
  }
  return crc >>> 0;
});

const crc32 = (buffer) => {
  let crc = 0xffffffff;
  for (const byte of buffer) {
    crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  }
  return (crc ^ 0xffffffff) >>> 0;
};

const chunk = (type, data) => {
  const typeBytes = Buffer.from(type, 'ascii');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const checksum = Buffer.alloc(4);
  checksum.writeUInt32BE(crc32(Buffer.concat([typeBytes, data])));
  return Buffer.concat([length, typeBytes, data, checksum]);
};

const png = (width, height, color) => {
  const signature = Buffer.from('89504e470d0a1a0a', 'hex');
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 6;
  const scanlines = Buffer.alloc((width * 4 + 1) * height);

  for (let row = 0; row < height; row += 1) {
    const start = row * (width * 4 + 1);
    scanlines[start] = 0;
    for (let column = 0; column < width; column += 1) {
      scanlines.set(color, start + 1 + column * 4);
    }
  }

  return Buffer.concat([
    signature,
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(scanlines)),
    chunk('IEND', Buffer.alloc(0)),
  ]);
};

const upload = (name, width, height, color) => ({
  name,
  mimeType: 'image/png',
  buffer: png(width, height, color),
});

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const createDraft = async (page, {
  title,
  slug,
  excerpt,
  body,
  image,
  alt,
  source,
  seoTitle = '',
  seoDescription = '',
}) => {
  await page.goto(`${backendBaseURL}/admin/news-articles/create`);
  await page.getByLabel('Título', { exact: true }).fill(title);
  await page.getByLabel('Slug').fill(slug);
  await page.getByLabel('Resumen').fill(excerpt);
  await page.getByLabel('Contenido').fill(body);
  await page.getByLabel(/Imagen principal/).setInputFiles(image);
  await page.getByLabel('Texto alternativo').fill(alt);
  await page.getByLabel('Procedencia editorial privada').fill(source);
  await page.getByLabel(/Confirmo que ya he verificado/).check();
  await page.getByLabel('Título SEO (opcional)').fill(seoTitle);
  await page.getByLabel('Descripción SEO (opcional)').fill(seoDescription);
  await page.getByRole('button', { name: 'Guardar noticia' }).click();
  await expect(page).toHaveURL(/\/admin\/news-articles\/\d+\/edit$/);
  await expect(page.getByText('Noticia creada como borrador.')).toBeVisible();
};

const publishOpenArticle = async (page, publishedAt = '') => {
  await page.getByLabel('Estado editorial').selectOption('published');
  await page.getByLabel('Fecha de publicación').fill(publishedAt);
  await page.getByLabel(/Confirmo que ya he verificado/).check();
  await page.getByRole('button', { name: 'Guardar noticia' }).click();
  await expect(page.getByText('Noticia actualizada correctamente.')).toBeVisible();
};

const openAdminArticle = async (page, title) => {
  await page.goto(`${backendBaseURL}/admin/news-articles`);
  const row = page.getByRole('row').filter({ hasText: title });
  await row.getByRole('link', { name: 'Editar' }).click();
  await expect(page.getByRole('heading', { name: 'Editar noticia' })).toBeVisible();
  await expect(page.getByText(title, { exact: true })).toBeVisible();
};

const deleteIfPresent = async (page, title) => {
  await page.goto(`${backendBaseURL}/admin/news-articles`);
  const row = page.getByRole('row').filter({ hasText: title });
  if (await row.count() === 0) return;

  page.once('dialog', (dialog) => dialog.accept());
  await row.getByRole('button', { name: 'Eliminar' }).click();
  await expect(page.getByRole('row').filter({ hasText: title })).toHaveCount(0);
};

test('publica Noticias desde Blade con orden, privacidad, SEO y lifecycle multimedia', async ({ page }) => {
  const titles = {
    first: 'Noticia E2E Primera',
    second: 'Noticia E2E Segunda',
    future: 'Noticia E2E Futura',
  };
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await loginAdmin(page);

  try {
    await createDraft(page, {
      title: titles.first,
      slug: 'noticia-e2e-primera',
      excerpt: 'Resumen público de la primera noticia E2E.',
      body: 'Primer párrafo de la primera noticia.\n\nSegundo párrafo de la primera noticia.',
      image: upload('news-first.png', 96, 54, [15, 95, 180, 255]),
      alt: 'Ilustración azul sin personas para la primera noticia.',
      source: 'Fixture E2E generada sin personas identificables.',
    });

    const draftResponse = await page.request.get(
      `${backendBaseURL}/api/v1/news/noticia-e2e-primera`,
    );
    expect(draftResponse.status()).toBe(404);
    await page.goto('/noticias');
    await expect(page.getByText(titles.first)).toHaveCount(0);

    await openAdminArticle(page, titles.first);
    await publishOpenArticle(page);

    await createDraft(page, {
      title: titles.second,
      slug: 'noticia-e2e-segunda',
      excerpt: 'Resumen público de la segunda noticia E2E.',
      body: 'Contenido público de la segunda noticia.',
      image: upload('news-second.png', 80, 50, [20, 150, 75, 255]),
      alt: 'Ilustración verde sin personas para la segunda noticia.',
      source: 'Fixture E2E generada sin personas identificables.',
      seoTitle: 'Segunda noticia E2E SEO',
      seoDescription: 'Descripción SEO de la segunda noticia E2E.',
    });
    await publishOpenArticle(page);

    await createDraft(page, {
      title: titles.future,
      slug: 'noticia-e2e-futura',
      excerpt: 'Resumen de una noticia futura E2E.',
      body: 'Esta noticia permanece programada y oculta.',
      image: upload('news-future.png', 64, 36, [170, 75, 25, 255]),
      alt: 'Ilustración naranja sin personas para una noticia futura.',
      source: 'Fixture E2E generada sin personas identificables.',
    });
    await publishOpenArticle(page, '2099-12-31T23:59');

    const listResponse = await page.request.get(`${backendBaseURL}/api/v1/news`);
    expect(listResponse.status()).toBe(200);
    const listPayload = await listResponse.json();
    expect(listPayload.data.map(({ slug }) => slug)).toEqual([
      'noticia-e2e-segunda',
      'noticia-e2e-primera',
    ]);
    expect(listPayload.meta.per_page).toBe(12);
    const serializedList = JSON.stringify(listPayload);
    for (const privateField of [
      'image_key',
      'image_source',
      'image_rights_confirmed',
      'status',
    ]) {
      expect(serializedList).not.toContain(privateField);
    }
    for (const articleSummary of listPayload.data) {
      expect(new URL(articleSummary.image.url).pathname)
        .toBe(`/api/v1/news/${articleSummary.slug}/image`);
      expect(new URL(articleSummary.image.url).search).toBe('');
    }

    await page.goto('/noticias');
    await expect(page.getByRole('heading', { name: 'Noticias', level: 1 })).toBeVisible();
    const featured = page.getByRole('article').filter({ hasText: 'Última noticia' });
    await expect(featured).toContainText(titles.second);
    await expect(page.getByRole('article').filter({ hasText: titles.first })).toBeVisible();
    await expect(page.getByText(titles.future)).toHaveCount(0);
    const newsNavigation = page.getByRole('navigation', { name: 'Navegación principal' })
      .getByRole('link', { name: 'Noticias' });
    await expect(newsNavigation).toHaveAttribute('aria-current', 'page');

    await featured.getByRole('link', { name: `Leer noticia: ${titles.second}` }).click();
    await expect(page).toHaveURL(/\/noticias\/noticia-e2e-segunda$/);
    await expect(page.getByRole('heading', { name: titles.second, level: 1 })).toBeVisible();
    await expect(page.getByRole('img', { name: /Ilustración verde/ })).toBeVisible();
    await expect(page.getByRole('time')).toBeVisible();
    await expect(page.getByText('Contenido público de la segunda noticia.')).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      'href',
      'https://example.test/noticias/noticia-e2e-segunda',
    );
    await expect(page.locator('meta[property="og:type"]')).toHaveAttribute('content', 'article');
    await expect(page.locator('meta[property="og:image"]'))
      .toHaveAttribute('content', /\/api\/v1\/news\/noticia-e2e-segunda\/image$/);
    const jsonLd = JSON.parse(
      await page.locator('script[data-public-seo-jsonld]').textContent(),
    );
    expect(jsonLd).toMatchObject({
      '@type': 'NewsArticle',
      headline: titles.second,
      author: { '@type': 'Organization', name: 'Club Galotxes Monòver' },
      publisher: { '@type': 'Organization', name: 'Club Galotxes Monòver' },
    });
    expect(JSON.stringify(await page.locator('main').textContent())).not.toContain('news/');

    const navbarNews = page.getByRole('navigation', { name: 'Navegación principal' })
      .getByRole('link', { name: 'Noticias' });
    await navbarNews.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/noticias$/);

    await page.setViewportSize({ width: 320, height: 800 });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);

    const firstImageUrl = listPayload.data.find(
      ({ slug }) => slug === 'noticia-e2e-primera',
    ).image.url;
    const originalImage = await page.request.get(firstImageUrl);
    expect(originalImage.status()).toBe(200);
    const originalBytes = await originalImage.body();

    await openAdminArticle(page, titles.first);
    await page.getByLabel(/Imagen principal nueva/).setInputFiles(
      upload('news-first-replacement.png', 54, 96, [130, 35, 175, 255]),
    );
    await page.getByLabel('Texto alternativo').fill('Ilustración violeta de reemplazo sin personas.');
    await page.getByLabel('Procedencia editorial privada')
      .fill('Segunda fixture E2E generada sin personas identificables.');
    await page.getByLabel(/Confirmo que ya he verificado/).check();
    await page.getByRole('button', { name: 'Guardar noticia' }).click();
    await expect(page.getByText('Noticia actualizada correctamente.')).toBeVisible();

    const replacementImage = await page.request.get(firstImageUrl);
    expect(replacementImage.status()).toBe(200);
    expect((await replacementImage.body()).equals(originalBytes)).toBe(false);
    await page.goto('/noticias');
    await expect(page.getByRole('img', { name: 'Ilustración violeta de reemplazo sin personas.' }))
      .toBeVisible();

    await deleteIfPresent(page, titles.first);
    expect((await page.request.get(`${backendBaseURL}/api/v1/news/noticia-e2e-primera`)).status())
      .toBe(404);
    expect((await page.request.get(firstImageUrl)).status()).toBe(404);

    expect(consoleErrors, `Errores de consola previos: ${consoleErrors.join('\n')}`).toEqual([]);
    await page.goto('/noticias/noticia-e2e-primera');
    await expect(page.getByRole('heading', { name: 'Página no encontrada' })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');

    expect(consoleErrors).toEqual([
      expect.stringContaining('404 (Not Found)'),
    ]);
  } finally {
    await deleteIfPresent(page, titles.first);
    await deleteIfPresent(page, titles.second);
    await deleteIfPresent(page, titles.future);
  }
});
