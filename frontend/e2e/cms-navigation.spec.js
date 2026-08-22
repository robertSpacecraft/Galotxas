import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const adminCredentials = {
  email: 'admin.e2e@example.test',
  password: 'E2E-password-123!',
};
const pageTitle = 'Historia temporal de navegación E2E';
const pageSlug = 'historia-navegacion-e2e';
const menuLabel = 'Historia E2E';

test.setTimeout(120_000);

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const openCmsPageEdit = async (page, title = pageTitle) => {
  await page.goto(`${backendBaseURL}/admin/cms/pages`);
  const row = page.getByRole('row').filter({ hasText: title });
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Editar' }).click();
};

const setCmsPageStatus = async (page, status) => {
  await openCmsPageEdit(page);
  await page.getByLabel('Estado').selectOption(status);
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.getByText('Página CMS actualizada correctamente.')).toBeVisible();
};

const selectCmsPage = async (page, title = pageTitle) => {
  const select = page.getByLabel('Página CMS');
  const value = await select.locator('option').filter({ hasText: title }).getAttribute('value');

  expect(value).not.toBeNull();
  await select.selectOption(value);
};

const openPlacementEdit = async (page) => {
  await page.goto(`${backendBaseURL}/admin/cms-navigation`);
  const row = page.getByRole('row').filter({ hasText: menuLabel });
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Editar' }).click();
};

const setPlacementActive = async (page, active) => {
  await openPlacementEdit(page);
  const checkbox = page.getByLabel('Activo en el menú público');
  if (active) {
    await checkbox.check();
  } else {
    await checkbox.uncheck();
  }
  await page.getByRole('button', { name: 'Guardar elemento' }).click();
  await expect(page.getByText('Elemento de navegación CMS actualizado correctamente.'))
    .toBeVisible();
};

const openClub = async (page) => {
  const club = page.getByRole('button', { name: 'Club' });
  if (!await club.isVisible()) {
    await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
  }
  await club.click();
  await expect(club).toHaveAttribute('aria-expanded', 'true');

  return club;
};

const expectPublicPlacement = async (page, visible) => {
  await page.goto('/');
  await openClub(page);
  const link = page.getByRole('link', { name: menuLabel });

  if (visible) {
    await expect(link).toBeVisible();
  } else {
    await expect(link).toHaveCount(0);
  }
};

test('administra y compone una página CMS en el slot protegido Club', async ({ page }) => {
  await loginAdmin(page);

  await page.goto(`${backendBaseURL}/admin/cms/pages/create`);
  await page.getByLabel('Título', { exact: true }).fill(pageTitle);
  await page.getByLabel('Slug').fill(pageSlug);
  await page.getByLabel('Título SEO').fill('Historia temporal E2E');
  await page.getByLabel('Descripción SEO').fill('Contenido temporal para navegación CMS E2E.');
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.getByText('Página CMS creada correctamente.')).toBeVisible();

  const pageRow = page.getByRole('row').filter({ hasText: pageTitle });
  await pageRow.getByRole('link', { name: 'Ver' }).click();
  await page.getByRole('link', { name: 'Crear bloque' }).first().click();
  await page.getByLabel('Tipo').selectOption('text');
  await page.getByLabel('Texto', { exact: true })
    .fill('Contenido temporal navegable desde Club.');
  await page.getByRole('button', { name: 'Guardar bloque' }).click();
  await expect(page.getByText('Bloque CMS creado correctamente.')).toBeVisible();

  await setCmsPageStatus(page, 'published');

  await page.goto(`${backendBaseURL}/admin/cms-navigation/create`);
  await selectCmsPage(page);
  await page.getByLabel('Etiqueta del menú').fill(menuLabel);
  await page.getByLabel('Orden entre páginas CMS').fill('10');
  await page.getByLabel('Activo en el menú público').check();
  await page.getByRole('button', { name: 'Guardar elemento' }).click();
  await expect(page.getByText('Elemento de navegación CMS creado correctamente.')).toBeVisible();

  const apiResponse = await page.request.get(`${backendBaseURL}/api/v1/cms-navigation`);
  expect(apiResponse.status()).toBe(200);
  expect(await apiResponse.json()).toEqual({
    message: null,
    data: [{
      slot: 'club',
      label: menuLabel,
      url: `/contenidos/${pageSlug}`,
      sort_order: 10,
    }],
  });

  await page.goto('/');
  const club = await openClub(page);
  const clubLinks = page.locator('#public-navigation-club-panel a');
  await expect(clubLinks).toHaveText([
    'Quiénes somos',
    'Contacto',
    'Federarse',
    'Documentos',
    menuLabel,
  ]);
  await page.getByRole('link', { name: menuLabel }).click();
  await expect(page).toHaveURL(new RegExp(`/contenidos/${pageSlug}$`));
  await expect(page.getByRole('heading', { name: pageTitle, level: 1 })).toBeVisible();
  await expect(page.getByText('Contenido temporal navegable desde Club.')).toBeVisible();
  await expect(club).toHaveClass(/navItemActive/);
  await expect(page.locator('#public-navigation-club-panel a').filter({ hasText: menuLabel }))
    .toHaveAttribute('aria-current', 'page');
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

  await setPlacementActive(page, false);
  await expectPublicPlacement(page, false);
  await setPlacementActive(page, true);
  await expectPublicPlacement(page, true);

  await setCmsPageStatus(page, 'draft');
  await expectPublicPlacement(page, false);
  await setCmsPageStatus(page, 'published');
  await expectPublicPlacement(page, true);

  await page.goto(`${backendBaseURL}/admin/cms/pages`);
  const reservedRow = page.getByRole('row').filter({ hasText: 'Contacto E2E' });
  const reservedEditHref = await reservedRow.getByRole('link', { name: 'Editar' }).getAttribute('href');
  const reservedId = reservedEditHref?.match(/\/cms\/pages\/(\d+)\/edit$/)?.[1];
  expect(reservedId).toBeTruthy();

  await page.goto(`${backendBaseURL}/admin/cms-navigation/create`);
  await page.getByLabel('Página CMS').evaluate((select, id) => {
    const option = document.createElement('option');
    option.value = id;
    option.textContent = 'Contacto E2E manipulado';
    select.append(option);
  }, reservedId);
  await page.getByLabel('Página CMS').selectOption(reservedId);
  await page.getByLabel('Etiqueta del menú').fill('Contacto duplicado');
  await page.getByLabel('Orden entre páginas CMS').fill('99');
  await page.getByRole('button', { name: 'Guardar elemento' }).click();
  await expect(page.getByText(
    'La página seleccionada ya dispone de un destino estructural protegido en Club.',
  ).first()).toBeVisible();

  await page.setViewportSize({ width: 320, height: 800 });
  await page.goto('/');
  await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
  const mobileClub = await openClub(page);
  await expect(page.getByRole('link', { name: menuLabel })).toBeVisible();
  await page.getByRole('link', { name: menuLabel }).focus();
  await page.keyboard.press('Escape');
  await expect(mobileClub).toBeFocused();
  await expect(mobileClub).toHaveAttribute('aria-expanded', 'false');

  await openPlacementEdit(page);
  page.once('dialog', (dialog) => dialog.accept());
  await page.getByRole('link', { name: 'Volver' }).click();
  const placementRow = page.getByRole('row').filter({ hasText: menuLabel });
  await placementRow.getByRole('button', { name: 'Eliminar' }).click();
  await expect(page.getByText('Elemento de navegación CMS eliminado correctamente.')).toBeVisible();

  await expectPublicPlacement(page, false);
  await setCmsPageStatus(page, 'draft');
});
