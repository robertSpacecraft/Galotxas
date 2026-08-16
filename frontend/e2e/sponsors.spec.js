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

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const upload = (name, width, height, color) => ({
  name,
  mimeType: 'image/png',
  buffer: png(width, height, color),
});

const createSponsor = async (page, {
  name,
  order,
  website = '',
  logo,
}) => {
  await page.goto(`${backendBaseURL}/admin/sponsors/create`);
  await page.getByLabel('Nombre público').fill(name);
  await page.getByLabel(/Logo/).setInputFiles(logo);
  await page.getByLabel('Web externa (opcional)').fill(website);
  await page.getByLabel('Orden').fill(String(order));
  await page.getByLabel('Activo').check();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page).toHaveURL(/\/admin\/sponsors$/);
  await expect(page.getByRole('row').filter({ hasText: name })).toBeVisible();
};

const editSponsor = async (page, name) => {
  await page.goto(`${backendBaseURL}/admin/sponsors`);
  const row = page.getByRole('row').filter({ hasText: name });
  await row.getByRole('link', { name: 'Editar' }).click();
  await expect(page.getByRole('heading', { name: 'Editar colaborador' })).toBeVisible();
};

const deleteSponsor = async (page, name) => {
  await page.goto(`${backendBaseURL}/admin/sponsors`);
  const row = page.getByRole('row').filter({ hasText: name });
  page.once('dialog', (dialog) => dialog.accept());
  await row.getByRole('button', { name: 'Eliminar' }).click();
  await expect(page).toHaveURL(/\/admin\/sponsors$/);
  await expect(page.getByRole('row').filter({ hasText: name })).toHaveCount(0);
};

test('administra y publica colaboradores antes del footer con lifecycle multimedia completo', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await loginAdmin(page);
  await createSponsor(page, {
    name: 'Colaborador E2E Alfa',
    order: 20,
    website: 'https://example.com/colaborador',
    logo: upload('alpha.png', 32, 16, [10, 90, 180, 255]),
  });
  await createSponsor(page, {
    name: 'Colaborador E2E Beta',
    order: 10,
    logo: upload('beta.png', 24, 24, [20, 150, 70, 255]),
  });

  await page.goto('/');
  const section = page.getByRole('region', { name: 'Colaboradores' });
  await expect(section).toBeVisible();
  await expect(section.locator('img')).toHaveCount(2);
  await expect(section.locator('img').first()).toHaveAttribute('loading', 'lazy');
  await expect(section.locator('img').first()).toHaveAttribute('decoding', 'async');
  await expect(section.locator('img').nth(0)).toHaveAttribute('alt', 'Colaborador E2E Beta');
  await expect(section.locator('img').nth(1)).toHaveAttribute('alt', 'Colaborador E2E Alfa');
  await expect(section.locator('img').nth(1)).toHaveAttribute('width', '32');
  await expect(section.locator('img').nth(1)).toHaveAttribute('height', '16');
  for (const logoUrl of await section.locator('img').evaluateAll(
    (images) => images.map((image) => image.src),
  )) {
    const logoResponse = await page.request.get(logoUrl);
    expect(logoResponse.status()).toBe(200);
    expect(logoResponse.headers()['content-type']).toBe('image/png');
  }
  await expect.poll(() => section.locator('img').evaluateAll(
    (images) => images.every((image) => image.complete && image.naturalWidth > 0),
  )).toBe(true);
  const externalLink = section.getByRole('link', { name: /Colaborador E2E Alfa/ });
  await expect(externalLink).toHaveAttribute('href', 'https://example.com/colaborador');
  await expect(externalLink).toHaveAttribute('target', '_blank');
  await expect(externalLink).toHaveAttribute('rel', 'sponsored noopener noreferrer');
  expect(await section.evaluate((element) => element.nextElementSibling?.tagName)).toBe('FOOTER');

  const originalLogoUrl = await section.getByAltText('Colaborador E2E Alfa').getAttribute('src');
  expect(originalLogoUrl).toBeTruthy();

  await editSponsor(page, 'Colaborador E2E Alfa');
  await page.getByLabel('Activo').uncheck();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await page.goto('/');
  await expect(page.getByAltText('Colaborador E2E Alfa')).toHaveCount(0);
  await expect(page.getByAltText('Colaborador E2E Beta')).toBeVisible();

  await editSponsor(page, 'Colaborador E2E Alfa');
  await page.getByLabel('Activo').check();
  await page.getByLabel(/Logo nuevo/).setInputFiles(
    upload('alpha-replacement.png', 18, 36, [180, 70, 20, 255]),
  );
  await page.getByRole('button', { name: 'Guardar' }).click();
  await page.goto('/');
  const replacement = page.getByAltText('Colaborador E2E Alfa');
  await expect(replacement).toBeVisible();
  await expect(replacement).toHaveAttribute('src', originalLogoUrl);
  await expect(replacement).toHaveAttribute('width', '18');
  await expect(replacement).toHaveAttribute('height', '36');
  await expect.poll(() => replacement.evaluate(
    (image) => image.complete && image.naturalWidth === 18 && image.naturalHeight === 36,
  )).toBe(true);

  await page.setViewportSize({ width: 320, height: 800 });
  await expect.poll(() => page.evaluate(
    () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
  )).toBe(true);

  await deleteSponsor(page, 'Colaborador E2E Alfa');
  const deletedLogo = await page.request.get(originalLogoUrl);
  expect(deletedLogo.status()).toBe(404);
  await page.goto('/');
  await expect(page.getByAltText('Colaborador E2E Alfa')).toHaveCount(0);
  await expect(page.getByAltText('Colaborador E2E Beta')).toBeVisible();

  await deleteSponsor(page, 'Colaborador E2E Beta');
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Colaboradores' })).toHaveCount(0);
  await expect(page.locator('main + section')).toHaveCount(0);

  expect(consoleErrors, `Errores de consola: ${consoleErrors.join('\n')}`).toEqual([]);
});
