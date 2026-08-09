import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const adminCredentials = {
  email: 'admin.e2e@example.test',
  password: 'E2E-password-123!',
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

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const fillAdultEnrollment = async (page, suffix) => {
  await page.getByLabel('Nombre completo del participante').fill(`Persona Adulta ${suffix}`);
  await page.getByLabel('Fecha de nacimiento').fill('1990-01-01');
  await page.getByLabel('Nivel solicitado (opcional)').selectOption({ label: 'Adultos E2E' });
  await page.getByLabel('Teléfono de contacto').fill('611 000 000');
  await page.getByLabel('Correo electrónico de contacto').fill(`adulto.${suffix}@example.test`);
  await page.getByLabel('He leído la información de privacidad de la inscripción').check();
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
    const schoolHomeLink = page.getByRole('link', { name: 'Ver Escuela' });
    await expect(schoolHomeLink).toHaveAttribute('href', '/escuela');

    const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });
    await expect(editorialNavigation.getByRole('link')).toHaveCount(2);
    const learnNavigationButton = editorialNavigation.getByRole('button', { name: 'Aprende' });
    await learnNavigationButton.click();
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
    await expect(learnNavigationButton).toHaveClass(/navItemActive/);
    await expect(editorialNavigation.getByRole('link', {
      name: 'Escuela de Galotxas',
      includeHidden: true,
    })).toHaveAttribute('aria-current', 'page');
    await expect(page).toHaveTitle('Escuela de Galotxas | Club Galotxes Monòver');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta niveles, horarios, ubicaciones e inscripciones de la Escuela de Galotxas.',
    );
    await expect(page.getByRole('link', { name: 'Consultar el Manual' }))
      .toHaveAttribute('href', '/aprende-a-jugar/manual');
    await expect(page.getByText('Escuela de Galotxas E2E')).toBeVisible();
    await expect(page.getByText('Programa operativo ficticio para validar la experiencia E2E.'))
      .toBeVisible();
    await expect(page.getByText(
      'Completa la solicitud y el equipo de Escuela revisará los datos antes de activarla.',
    )).toBeVisible();
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
    await expect(page.getByText('600 111 222')).toHaveCount(0);
    await expect(page.getByText('escuela.e2e@example.test')).toHaveCount(0);
    await expect(page.getByText(/school_program_id|admin_notes|is_public/)).toHaveCount(0);
    const overview = await page.request.get(`${backendBaseURL}/api/v1/school`);
    expect(overview.ok()).toBe(true);
    const overviewBody = await overview.json();
    expect(overviewBody.data).not.toHaveProperty('contact');
    expect(JSON.stringify(overviewBody)).not.toContain('escuela.e2e@example.test');

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
    await page.getByLabel('He leído la información de privacidad de la inscripción').check();
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
    await expect(page.getByText('La solicitud de inscripción se ha recibido correctamente.'))
      .toBeVisible();
    await expect(page.getByText(/pending|id de solicitud|estado interno/)).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('una solicitud real de adulto no muestra ni envía representante', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);
    let enrollmentRequests = 0;
    page.on('request', (request) => {
      if (request.method() === 'POST' && request.url().endsWith('/api/v1/school/enrollments')) {
        enrollmentRequests += 1;
      }
    });

    await page.goto('/escuela');
    await fillAdultEnrollment(page, 'e2e');
    await expect(page.getByRole('group', { name: 'Representante' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Enviar solicitud' }).dblclick();

    await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
    await expect(page.getByText('La solicitud de inscripción se ha recibido correctamente.'))
      .toBeVisible();
    expect(enrollmentRequests).toBe(1);

    assertNoConsoleErrors();
  });

  test('validación local y cierre concurrente conservan foco, datos y reintento explícito', async ({ page }) => {
    await page.goto('/escuela');
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();
    await expect(page.getByLabel('Nombre completo del participante')).toBeFocused();
    await expect(page.getByLabel('Nombre completo del participante'))
      .toHaveAttribute('aria-describedby', 'participant_name-error');

    await page.getByLabel('Nombre completo del participante').fill('Datos inválidos E2E');
    await page.getByLabel('Fecha de nacimiento').fill('2999-01-01');
    await page.getByLabel('Teléfono de contacto').fill('teléfono inválido');
    await page.getByLabel('Correo electrónico de contacto').fill('correo-inválido');
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();
    await expect(page.getByText('Indica una fecha de nacimiento válida y no futura.'))
      .toBeVisible();
    await expect(page.getByText('Indica un teléfono de contacto válido.')).toBeVisible();
    await expect(page.getByText('Indica un correo electrónico válido.')).toBeVisible();

    await page.getByLabel('Nombre completo del participante').clear();
    await page.getByLabel('Fecha de nacimiento').clear();
    await page.getByLabel('Teléfono de contacto').clear();
    await page.getByLabel('Correo electrónico de contacto').clear();
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

    await fillAdultEnrollment(page, 'honeypot');
    await page.locator('input[name="website"]').fill('https://bot.example.test', { force: true });
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();
    await expect(page.getByRole('heading', { name: 'Solicitud recibida', level: 3 })).toBeVisible();
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
      body.data.enrollment_status = 'closed';
      await route.fulfill({ response, json: body });
    });
    await page.reload();
    await expect(page.getByText('No se admiten solicitudes de inscripción en este momento.'))
      .toBeVisible();
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toHaveCount(0);

    await page.unroute('**/api/v1/school');
    await page.route('**/api/v1/school', async (route) => {
      const response = await route.fetch();
      const body = await response.json();
      body.data.enrollments_open = false;
      body.data.enrollment_status = 'unavailable';
      await route.fulfill({ response, json: body });
    });
    await page.reload();
    await expect(page.getByText(/configuración operativa/)).toBeVisible();
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
        .getByRole('button', { name: 'Aprende' }),
    ).toHaveClass(/navItemActive/);
    await expect(page.getByRole('list', { name: 'Navegación editorial' }).getByRole('link', {
      name: 'Escuela de Galotxas',
      includeHidden: true,
    })).not.toHaveAttribute('aria-current');
  });

  test('administración bloquea configuración incompleta y controla cierre, apertura e histórico', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${backendBaseURL}/admin/school/programs`);
    const programRow = page.getByRole('row').filter({ hasText: 'Escuela de Galotxas E2E' });
    await expect(programRow).toContainText('Abiertas públicamente');
    await programRow.getByRole('link', { name: 'Editar' }).click();

    const description = page.getByLabel('Presentación pública');
    await description.clear();
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('listitem').filter({
      hasText: /No se pueden abrir las inscripciones/,
    })).toBeVisible();

    await description.fill('Programa operativo ficticio para validar la experiencia E2E.');
    await page.getByLabel('Inscripciones declaradas abiertas').uncheck();
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('row').filter({ hasText: 'Escuela de Galotxas E2E' }))
      .toContainText('Cerradas públicamente');

    await page.goto('/escuela');
    await expect(page.getByText('No se admiten solicitudes de inscripción en este momento.'))
      .toBeVisible();
    await expect(page.getByRole('button', { name: 'Enviar solicitud' })).toHaveCount(0);

    await page.goto(`${backendBaseURL}/admin/school/programs`);
    await page.getByRole('row').filter({ hasText: 'Escuela de Galotxas E2E' })
      .getByRole('link', { name: 'Editar' }).click();
    await page.getByLabel('Inscripciones declaradas abiertas').check();
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('row').filter({ hasText: 'Escuela de Galotxas E2E' }))
      .toContainText('Abiertas públicamente');

    await page.goto(`${backendBaseURL}/admin/school/enrollments`);
    await expect(page.getByText('Persona Adulta cierre')).toHaveCount(0);
    await expect(page.getByText('Persona Adulta honeypot')).toHaveCount(0);
    const minorRow = page.getByRole('row').filter({ hasText: 'Participante Menor E2E' });
    await minorRow.getByRole('link', { name: 'Ver detalle' }).click();
    await expect(page.getByText('NOTICE-SCHOOL-ENROLLMENT — versión 1.0.0')).toBeVisible();
    await page.getByLabel('Nivel obligatorio al aprobar').selectOption({ label: 'Iniciación E2E' });
    await page.getByRole('button', { name: 'Aprobar y activar' }).click();
    await expect(page.getByText('Activa', { exact: true })).toBeVisible();
    await expect(page.getByText(/por Admin/).first()).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Dar de baja' }).click();
    await expect(page.locator('span.badge').filter({ hasText: /^\s*Baja\s*$/ })).toBeVisible();
    await expect(page.getByText(/por Admin/).first()).toBeVisible();

    await page.goto(`${backendBaseURL}/admin/school/enrollments`);
    await page.getByLabel('Estado').selectOption('withdrawn');
    await page.getByRole('button', { name: 'Filtrar' }).click();
    await expect(page.getByRole('row').filter({ hasText: 'Participante Menor E2E' })).toBeVisible();
  });

  test('un centro conserva dos actividades sin crear identidades individuales', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${backendBaseURL}/admin/school/educational-centers/create`);
    await page.getByLabel('Nombre', { exact: true }).fill('Centro educativo ficticio E2E');
    await page.getByLabel('Localidad').fill('Monóvar E2E');
    await page.getByLabel('Centro activo').check();
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('heading', { name: 'Centro educativo ficticio E2E' })).toBeVisible();

    for (const [name, date, students] of [
      ['Actividad escolar E2E uno', '2026-10-10', '24'],
      ['Actividad escolar E2E dos', '2026-11-14', '31'],
    ]) {
      await page.getByRole('link', { name: 'Crear actividad' }).click();
      await page.getByLabel('Nombre de la actividad').fill(name);
      await page.getByLabel('Fecha').fill(date);
      await page.getByLabel('Alumnado previsto').fill(students);
      await expect(page.getByLabel(/Nombre del alumno|Participante individual/)).toHaveCount(0);
      await page.getByRole('button', { name: 'Guardar' }).click();
      await expect(page.getByRole('heading', { name })).toBeVisible();
      await page.getByRole('link', { name: 'Centro educativo ficticio E2E' }).click();
    }

    await expect(page.getByText('2 actividades')).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: 'Actividad escolar E2E uno' }))
      .toContainText('24');
    await expect(page.getByRole('row').filter({ hasText: 'Actividad escolar E2E dos' }))
      .toContainText('31');
  });
});
