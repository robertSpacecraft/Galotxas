import { deflateSync } from 'node:zlib';
import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const credentials = {
  email: 'player1.e2e@example.test',
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

const login = async (page) => {
  await page.goto('/login');
  await page.getByLabel('Correo Electrónico').fill(credentials.email);
  await page.getByLabel('Contraseña').fill(credentials.password);
  await page.getByRole('button', { name: 'Iniciar Sesión' }).click();

  await expect(page).toHaveURL(/\/player$/);
  await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
};

const assertNoPrivateReference = (payload) => {
  const serialized = JSON.stringify(payload);
  expect(serialized).not.toContain('profile_photo_path');
  expect(serialized).not.toContain('avatars/');
};

const authenticatedHeaders = async (page) => {
  const token = await page.evaluate(() => localStorage.getItem('token'));
  expect(token).toBeTruthy();

  return {
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  };
};

test('gestiona la foto privada en Mi Panel sin exponer claves ni identidad pública', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await login(page);
  const photoSection = page.getByRole('region', { name: 'Foto de perfil' });
  await expect(photoSection).toBeVisible();
  await expect(photoSection.getByRole('img', { name: 'Sin foto de perfil' })).toContainText('JU');
  await expect(photoSection.getByText('Es privada y no se publica en competición.')).toBeVisible();

  const firstUpload = page.waitForResponse((response) => (
    response.request().method() === 'POST'
      && new URL(response.url()).pathname === '/api/v1/me/profile-photo'
  ));
  await photoSection.getByLabel('Subir foto').setInputFiles(
    upload('avatar-horizontal.png', 48, 24, [20, 100, 190, 255]),
  );
  await expect(photoSection.getByText('Vista previa pendiente de guardar.')).toBeVisible();
  await photoSection.getByRole('button', { name: 'Guardar foto' }).click();

  const firstUploadResponse = await firstUpload;
  expect(firstUploadResponse.status()).toBe(200);
  const firstUploadPayload = await firstUploadResponse.json();
  expect(firstUploadPayload).toEqual({
    message: 'Foto de perfil actualizada correctamente.',
    data: {
      profile_photo: {
        url: expect.stringMatching(/\/api\/v1\/me\/profile-photo\/image$/),
      },
    },
  });
  assertNoPrivateReference(firstUploadPayload);
  await expect(photoSection.getByRole('status')).toContainText('Foto de perfil actualizada correctamente.');

  const avatar = photoSection.getByRole('img', { name: 'Foto de perfil de Jugador Uno E2E' });
  await expect(avatar).toBeVisible();
  await expect.poll(() => avatar.evaluate((image) => ({
    width: image.naturalWidth,
    height: image.naturalHeight,
    source: image.src.startsWith('blob:'),
  }))).toEqual({ width: 48, height: 24, source: true });

  const headers = await authenticatedHeaders(page);
  const meResponse = await page.request.get(`${backendBaseURL}/api/v1/me`, { headers });
  expect(meResponse.status()).toBe(200);
  const mePayload = await meResponse.json();
  expect(mePayload.data.user.profile_photo.url).toMatch(/\/api\/v1\/me\/profile-photo\/image$/);
  assertNoPrivateReference(mePayload);

  for (const path of ['/api/v1/championships', '/api/v1/rankings/all-time']) {
    const response = await page.request.get(`${backendBaseURL}${path}`);
    expect(response.status()).toBe(200);
    const payload = await response.json();
    expect(JSON.stringify(payload)).not.toContain('profile_photo');
    assertNoPrivateReference(payload);
  }

  const replacementUpload = page.waitForResponse((response) => (
    response.request().method() === 'POST'
      && new URL(response.url()).pathname === '/api/v1/me/profile-photo'
  ));
  await photoSection.getByLabel('Cambiar foto').setInputFiles(
    upload('avatar-vertical.png', 24, 48, [180, 80, 30, 255]),
  );
  await photoSection.getByRole('button', { name: 'Guardar foto' }).click();
  const replacementPayload = await (await replacementUpload).json();
  assertNoPrivateReference(replacementPayload);
  await expect.poll(() => avatar.evaluate((image) => ({
    width: image.naturalWidth,
    height: image.naturalHeight,
    source: image.src.startsWith('blob:'),
  }))).toEqual({ width: 24, height: 48, source: true });

  await page.setViewportSize({ width: 320, height: 800 });
  await expect(photoSection.getByLabel('Cambiar foto')).toBeVisible();
  await expect(photoSection.getByRole('button', { name: 'Eliminar foto' })).toBeVisible();
  await expect.poll(() => page.evaluate(
    () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
  )).toBe(true);

  const deleteResponse = page.waitForResponse((response) => (
    response.request().method() === 'DELETE'
      && new URL(response.url()).pathname === '/api/v1/me/profile-photo'
  ));
  page.once('dialog', (dialog) => dialog.accept());
  await photoSection.getByRole('button', { name: 'Eliminar foto' }).click();
  const deletePayload = await (await deleteResponse).json();
  expect(deletePayload).toEqual({
    message: 'Foto de perfil eliminada correctamente.',
    data: { profile_photo: null },
  });
  assertNoPrivateReference(deletePayload);
  await expect(photoSection.getByRole('status')).toContainText('Foto de perfil eliminada correctamente.');
  await expect(photoSection.getByRole('img', { name: 'Sin foto de perfil' })).toContainText('JU');

  const missingImage = await page.request.get(
    `${backendBaseURL}/api/v1/me/profile-photo/image`,
    { headers },
  );
  expect(missingImage.status()).toBe(404);
  expect(consoleErrors, `Errores de consola: ${consoleErrors.join('\n')}`).toEqual([]);
});
