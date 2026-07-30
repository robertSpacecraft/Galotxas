import { expect, test } from '@playwright/test';

const watchCriticalConsoleErrors = (page) => {
  const errors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });

  return () => expect(errors, `Errores críticos de consola: ${errors.join('\n')}`).toEqual([]);
};

const fillAdultEnrollment = async (page, suffix) => {
  await page.getByLabel('Nombre completo del participante').fill(`Persona Adulta ${suffix}`);
  await page.getByLabel('Fecha de nacimiento').fill('1990-01-01');
  await page.getByLabel('Nivel solicitado (opcional)').selectOption({ label: 'Adultos E2E' });
  await page.getByLabel('Teléfono de contacto').fill('611 000 000');
  await page.getByLabel('Correo electrónico de contacto').fill(`adulto.${suffix}@example.test`);
};

test.describe.serial('experiencia pública de Escuela de Galotxas', () => {
  test('Home, Navbar, carga diferida y agregado público conectan la landing responsive', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);
    let releaseSchoolModule;
    const schoolModuleGate = new Promise((resolve) => {
      releaseSchoolModule = resolve;
    });

    await page.route('**/src/features/school/SchoolPage.jsx*', async (route) => {
      await schoolModuleGate;
      await route.continue();
    });

    await page.goto('/');
    await expect(page.getByText('Academy', { exact: true })).toHaveCount(0);
    const schoolHomeLink = page.getByRole('link', { name: /Escuela de Galotxas/ }).last();
    await expect(schoolHomeLink).toHaveAttribute('href', '/escuela');

    const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });
    await expect(editorialNavigation.getByRole('link')).toHaveCount(4);
    const schoolNavigationLink = editorialNavigation.getByRole('link', {
      name: 'Escuela de Galotxas',
    });
    await schoolNavigationLink.click();

    await expect(page.getByRole('status')).toContainText('Cargando Escuela de Galotxas');
    await expect(page.locator('main h1')).toHaveCount(0);
    await expect(page.getByText('Página no encontrada')).toHaveCount(0);
    releaseSchoolModule();

    await expect(page).toHaveURL(/\/escuela$/);
    await expect(page.getByRole('heading', { name: 'Escuela de Galotxas', level: 1 })).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(schoolNavigationLink).toHaveAttribute('aria-current', 'page');
    await expect(page).toHaveTitle('Escuela de Galotxas | Galotxas');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta niveles, horarios, ubicaciones e inscripciones de la Escuela de Galotxas.',
    );
    await expect(page.getByRole('link', { name: 'Consultar el Manual' }))
      .toHaveAttribute('href', '/aprende-a-jugar/manual');
    await expect(page.getByText('Escuela de Galotxas E2E')).toBeVisible();
    await expect(page.getByText('Inscripciones abiertas.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Iniciación E2E', level: 3 })).toBeVisible();
    await expect(page.getByText('De 8 a 17 años')).toBeVisible();
    await expect(page.getByText('Martes')).toBeVisible();
    await expect(page.getByText('17:00–18:30')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Adultos E2E', level: 3 })).toBeVisible();
    await expect(page.getByText('Desde 18 años')).toBeVisible();
    await expect(page.getByText('Jueves')).toBeVisible();
    await expect(page.getByText('19:00–20:30')).toBeVisible();
    await expect(page.getByText('Pista Escuela E2E').first()).toBeVisible();
    await expect(page.getByRole('link', { name: '600 111 222' }))
      .toHaveAttribute('href', 'tel:600 111 222');
    await expect(page.getByRole('link', { name: 'escuela.e2e@example.test' }))
      .toHaveAttribute('href', 'mailto:escuela.e2e@example.test');
    await expect(page.getByText(/school_program_id|admin_notes|is_public/)).toHaveCount(0);

    for (const width of [320, 375, 768, 1024, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      )).toBe(true);
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => {
      document.body.style.zoom = '200%';
    });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
    await page.evaluate(() => {
      document.body.style.zoom = '';
    });

    assertNoConsoleErrors();
  });

  test('una solicitud real de menor exige representante y confirma sólo la recepción', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/escuela');
    await page.getByLabel('Nombre completo del participante').fill('Participante Menor E2E');
    await page.getByLabel('Fecha de nacimiento').fill('2012-08-01');
    await expect(page.getByRole('group', { name: 'Representante' })).toBeVisible();
    await page.getByLabel('Nivel solicitado (opcional)').selectOption({ label: 'Iniciación E2E' });
    await page.getByLabel('Teléfono de contacto').fill('600 123 123');
    await page.getByLabel('Correo electrónico de contacto').fill('familia.e2e@example.test');
    await page.getByLabel('Nombre completo del representante').fill('Persona Tutora E2E');
    await page.getByLabel('Relación con el participante').fill('Madre');
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
    await expect(page.getByText('La solicitud de inscripción se ha recibido correctamente.'))
      .toBeVisible();
    await expect(page.getByText(/pending|id de solicitud|estado interno/)).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('una solicitud real de adulto no muestra ni envía representante', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/escuela');
    await fillAdultEnrollment(page, 'e2e');
    await expect(page.getByRole('group', { name: 'Representante' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
    await expect(page.getByText('La solicitud de inscripción se ha recibido correctamente.'))
      .toBeVisible();

    assertNoConsoleErrors();
  });

  test('validación local y cierre concurrente conservan foco, datos y reintento explícito', async ({ page }) => {
    await page.goto('/escuela');
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();
    await expect(page.getByLabel('Nombre completo del participante')).toBeFocused();
    await expect(page.getByLabel('Nombre completo del participante'))
      .toHaveAttribute('aria-describedby', 'participant_name-error');

    await fillAdultEnrollment(page, 'cierre');
    await page.route('**/api/v1/school/enrollments', async (route) => {
      await route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'La inscripción no está disponible actualmente.',
          data: null,
        }),
      });
    });
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page.getByRole('alert')).toContainText(
      'La inscripción no está disponible actualmente.',
    );
    await expect(page.getByLabel('Nombre completo del participante')).toBeDisabled();
    await expect(page.getByLabel('Nombre completo del participante'))
      .toHaveValue('Persona Adulta cierre');
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toBeDisabled();
    await page.unroute('**/api/v1/school/enrollments');
    await page.getByRole('button', { name: 'Volver a comprobar disponibilidad' }).click();
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toBeEnabled();
  });

  test('ausencia, cierre de lectura, error recuperable y descendiente no aprobado son seguros', async ({ page }) => {
    await page.route('**/api/v1/school', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: null, data: null }),
      });
    });
    await page.goto('/escuela');
    await expect(page.getByText('La información de la Escuela no está disponible actualmente.'))
      .toBeVisible();
    await expect(page.getByRole('link', { name: 'Consultar el Manual' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toHaveCount(0);

    await page.unroute('**/api/v1/school');
    await page.route('**/api/v1/school', async (route) => {
      const response = await route.fetch();
      const body = await response.json();
      body.data.enrollments_open = false;
      await route.fulfill({ response, json: body });
    });
    await page.reload();
    await expect(page.getByText('No se admiten solicitudes de inscripción en este momento.'))
      .toBeVisible();
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toHaveCount(0);

    await page.unroute('**/api/v1/school');
    await page.route('**/api/v1/school', async (route) => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Detalle interno' }),
      });
    });
    await page.reload();
    await expect(page.getByRole('alert')).toContainText(
      'No se ha podido cargar la información de la Escuela.',
    );
    await expect(page.getByText('Detalle interno')).toHaveCount(0);
    await page.unroute('**/api/v1/school');
    await page.getByRole('button', { name: 'Reintentar' }).click();
    await expect(page.getByText('Escuela de Galotxas E2E')).toBeVisible();

    await page.goto('/escuela/alumno');
    await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 })).toBeVisible();
    await expect(
      page.getByRole('list', { name: 'Navegación editorial' })
        .getByRole('link', { name: 'Escuela de Galotxas' }),
    ).toHaveAttribute('aria-current', 'location');
  });
});
