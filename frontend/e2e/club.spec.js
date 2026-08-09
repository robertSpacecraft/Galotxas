import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const adminCredentials = {
  email: 'admin.e2e@example.test',
  password: 'E2E-password-123!',
};

const clubPages = [
  {
    path: '/club/quienes-somos',
    legacyPath: '/contenidos/nosotros',
    slug: 'nosotros',
    title: 'Quiénes somos E2E',
    heading: 'Identidad institucional ficticia E2E',
  },
  {
    path: '/club/contacto',
    legacyPath: '/contenidos/contacto',
    slug: 'contacto',
    title: 'Contacto E2E',
    heading: 'Canales de contacto ficticios E2E',
  },
  {
    path: '/club/federarse',
    legacyPath: '/contenidos/federarse',
    slug: 'federarse',
    title: 'Federarse E2E',
    heading: 'Información federativa ficticia E2E',
  },
  {
    path: '/club/documentos',
    legacyPath: '/contenidos/documentos',
    slug: 'documentos',
    title: 'Documentos E2E',
    heading: 'Documentación institucional ficticia E2E',
  },
];

const fillContactForm = async (page) => {
  await page.getByLabel('Nombre', { exact: false }).fill('Persona E2E');
  await page.getByLabel('Correo electrónico', { exact: false })
    .fill(`contacto-${Date.now()}@example.test`);
  await page.getByLabel('Asunto', { exact: false }).fill('Consulta desde Playwright');
  await page.getByLabel('Mensaje', { exact: false })
    .fill('Este mensaje se genera sólo en la base temporal de las pruebas E2E.');
  await page.getByRole('checkbox').check();
};

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin/login`);
  await page.getByLabel('Email').fill(adminCredentials.email);
  await page.getByLabel('Contraseña').fill(adminCredentials.password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

test.describe.serial('fachadas públicas de Club', () => {
  test('carga Club de forma diferida con un fallback accesible', async ({ page }) => {
    let releaseClubModule;
    const clubModuleGate = new Promise((resolve) => {
      releaseClubModule = resolve;
    });

    await page.route('**/src/features/club/ClubPage.jsx*', async (route) => {
      await clubModuleGate;
      await route.continue();
    });

    await page.goto('/');
    await page.evaluate(() => {
      window.history.pushState({}, '', '/club/quienes-somos');
      window.dispatchEvent(new PopStateEvent('popstate'));
    });

    await expect(page.getByRole('status')).toContainText('Cargando Club');
    await expect(page.locator('main h1')).toHaveCount(0);
    releaseClubModule();
    await expect(page.getByRole('heading', { name: 'Quiénes somos E2E', level: 1 }))
      .toBeVisible();
  });

  test('las cuatro rutas consumen su slug CMS cerrado y aplican metadatos', async ({ page }) => {
    for (const clubPage of clubPages) {
      await page.goto(clubPage.path);

      await expect(page).toHaveURL(new RegExp(`${clubPage.path}$`));
      await expect(page.getByRole('heading', { name: clubPage.title, level: 1 })).toBeVisible();
      await expect(page.locator('main h1')).toHaveCount(1);
      await expect(page.getByRole('heading', { name: clubPage.heading, level: 2 })).toBeVisible();
      await expect(page.getByText(`Contenido CMS de prueba exclusivo de la fachada ${clubPage.slug}.`))
        .toBeVisible();
      await expect(page).toHaveTitle(`${clubPage.title} | Club Galotxes Monòver`);
      await expect(page.locator('meta[name="description"]')).toHaveAttribute(
        'content',
        `Escenario técnico aislado para ${clubPage.slug}.`,
      );
    }
  });

  test('una página CMS ausente usa el 404 y no muestra otra fachada', async ({ page }) => {
    await page.route('**/api/v1/cms/pages/federarse', (route) => route.fulfill({
      status: 404,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'No encontrado.', data: null }),
    }));

    await page.goto('/club/federarse');

    await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeVisible();
    await expect(page.getByText('Contenido CMS de prueba exclusivo de la fachada nosotros.'))
      .toHaveCount(0);
  });

  test('conserva las rutas CMS legadas y la página histórica /nosotros', async ({ page }) => {
    for (const clubPage of clubPages) {
      await page.goto(clubPage.legacyPath);
      await expect(page.getByRole('heading', { name: clubPage.title, level: 1 })).toBeVisible();
      await expect(page.getByText(`Contenido CMS de prueba exclusivo de la fachada ${clubPage.slug}.`))
        .toBeVisible();
    }

    await page.goto('/nosotros');
    await expect(page.getByRole('heading', {
      name: 'Mucho más que un juego: la tradición viva de Monóvar.',
      level: 1,
    })).toBeVisible();
  });

  test('preserva el CMS y oculta los campos cuando config=false', async ({ page }) => {
    await page.route('**/api/v1/contact/config', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ message: null, data: { enabled: false } }),
    }));

    await page.goto('/club/contacto');

    await expect(page.getByText('Contenido CMS de prueba exclusivo de la fachada contacto.'))
      .toBeVisible();
    await expect(page.getByText(/El formulario no está disponible actualmente/)).toBeVisible();
    await expect(page.getByLabel('Nombre', { exact: false })).toHaveCount(0);
  });

  test('muestra el formulario habilitado y admite navegación básica por teclado', async ({ page }) => {
    await page.goto('/club/contacto');

    const name = page.getByLabel('Nombre', { exact: false });
    const email = page.getByLabel('Correo electrónico', { exact: false });
    const consent = page.getByRole('checkbox');
    await expect(page.getByText('Club Galotxes de Monover', { exact: false }).first()).toBeVisible();
    await expect(page.getByText(/Aviso NOTICE-CONTACT-FORM, versión 1.0.0/)).toBeVisible();
    await expect(page.getByRole('link', { name: /Política de privacidad/ }))
      .toHaveAttribute('target', '_blank');
    await expect(consent).not.toBeChecked();
    await expect(name).toBeVisible();
    await name.focus();
    await expect(name).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(email).toBeFocused();
  });

  test('mantiene datos, errores asociados y foco ante una respuesta 422', async ({ page }) => {
    await page.route('**/api/v1/contact-requests', (route) => route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({
        message: 'Revisa los campos indicados.',
        errors: { email: ['El correo E2E debe revisarse.'] },
      }),
    }));
    await page.goto('/club/contacto');
    await fillContactForm(page);

    await page.getByRole('button', { name: 'Enviar mensaje' }).click();

    const email = page.getByLabel('Correo electrónico', { exact: false });
    await expect(page.getByText('El correo E2E debe revisarse.')).toBeVisible();
    await expect(email).toBeFocused();
    await expect(page.getByLabel('Nombre', { exact: false })).toHaveValue('Persona E2E');
    await expect(page.getByLabel('Mensaje', { exact: false }))
      .toHaveValue('Este mensaje se genera sólo en la base temporal de las pruebas E2E.');
  });

  test('envía una solicitud real 201 y limpia el formulario', async ({ page }) => {
    await page.goto('/club/contacto');
    await fillContactForm(page);

    await page.getByRole('button', { name: 'Enviar mensaje' }).click();

    const result = page.getByRole('status').filter({ hasText: 'Mensaje recibido' });
    await expect(result).toBeVisible();
    await expect(result).toBeFocused();
    await expect(page.getByLabel('Nombre', { exact: false })).toHaveCount(0);
  });

  test('acredita versión, consentimiento, correo fake y cierre desde Blade', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${backendBaseURL}/admin/contact-requests`);

    const row = page.getByRole('row').filter({ hasText: 'Consulta desde Playwright' });
    await expect(row).toBeVisible();
    await expect(row.getByText('Enviada')).toBeVisible();
    await row.getByRole('link', { name: 'Ver detalle' }).click();

    await expect(page.getByText('NOTICE-CONTACT-FORM')).toBeVisible();
    await expect(page.getByText('Versión 1.0.0')).toBeVisible();
    await expect(page.getByText('Consentimiento registrado')).toBeVisible();
    await expect(page.getByText('1 intento(s)')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Cerrar', exact: true }).click();

    await expect(page.getByText('La solicitud se ha cerrado conservando su contenido original.'))
      .toBeVisible();
    await expect(page.getByText('No iniciado')).toHaveCount(0);
  });

  test('mantiene 404 para /club y todos los descendientes desconocidos', async ({ page }) => {
    for (const pathname of [
      '/club',
      '/club/quienes-somos/desconocido',
      '/club/contacto/desconocido',
      '/club/federarse/desconocido',
      '/club/documentos/desconocido',
    ]) {
      await page.goto(pathname);
      await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeVisible();
      await expect(page).toHaveURL(new RegExp(`${pathname}$`));
    }
  });

  test('las cuatro fachadas evitan overflow crítico en móvil, tablet y escritorio', async ({ page }) => {
    for (const viewport of [
      { width: 320, height: 740 },
      { width: 768, height: 900 },
      { width: 1280, height: 900 },
    ]) {
      await page.setViewportSize(viewport);

      for (const clubPage of clubPages) {
        await page.goto(clubPage.path);
        await expect(page.getByRole('heading', { name: clubPage.title, level: 1 })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth))
          .toBe(true);
      }
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/club/contacto');
    await page.evaluate(() => {
      document.body.style.zoom = '200%';
    });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
    await page.evaluate(() => {
      document.body.style.zoom = '';
    });
  });

  test('expone las cuatro fachadas canónicas bajo el disclosure Club', async ({ page }) => {
    await page.goto('/club/quienes-somos');
    const navigation = page.getByRole('list', { name: 'Navegación editorial' });
    const clubButton = navigation.getByRole('button', { name: 'Club' });

    await expect(clubButton).toHaveClass(/navItemActive/);
    await clubButton.click();
    await expect(navigation.getByRole('link', { name: 'Quiénes somos' }))
      .toHaveAttribute('href', '/club/quienes-somos');
    await expect(navigation.getByRole('link', { name: 'Contacto' }))
      .toHaveAttribute('href', '/club/contacto');
    await expect(navigation.getByRole('link', { name: 'Federarse' }))
      .toHaveAttribute('href', '/club/federarse');
    await expect(navigation.getByRole('link', { name: 'Documentos' }))
      .toHaveAttribute('href', '/club/documentos');
    await expect(navigation.getByRole('link', { name: 'Club' })).toHaveCount(0);
  });
});
