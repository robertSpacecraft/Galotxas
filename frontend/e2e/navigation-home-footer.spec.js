import { expect, test } from '@playwright/test';

const playerCredentials = {
  email: 'player1.e2e@example.test',
  password: 'E2E-password-123!',
};

test.describe('navegación agrupada, Home y footer', () => {
  test('desktop opera ambos disclosures, mantiene uno abierto y restaura el foco con Escape', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/');

    const navigation = page.getByRole('list', { name: 'Navegación editorial' });
    const learnButton = navigation.getByRole('button', { name: 'Aprende' });
    const clubButton = navigation.getByRole('button', { name: 'Club' });

    await learnButton.click();
    await expect(learnButton).toHaveAttribute('aria-expanded', 'true');
    await expect(navigation.getByRole('link', { name: 'Manual y reglas' })).toBeVisible();

    await clubButton.click();
    await expect(learnButton).toHaveAttribute('aria-expanded', 'false');
    await expect(clubButton).toHaveAttribute('aria-expanded', 'true');
    await expect(navigation.getByRole('link', { name: 'Quiénes somos' })).toBeVisible();

    await learnButton.click();
    const manualLink = navigation.getByRole('link', { name: 'Manual y reglas' });
    await manualLink.focus();
    await page.keyboard.press('Escape');
    await expect(learnButton).toBeFocused();
    await expect(learnButton).toHaveAttribute('aria-expanded', 'false');

    await learnButton.click();
    await navigation.getByRole('link', { name: 'Manual y reglas' }).click();
    await expect(page).toHaveURL(/\/aprende-a-jugar\/manual$/);
    await expect(page.getByRole('heading', { name: 'Manual', level: 1 })).toBeVisible();
    await expect(learnButton).toHaveAttribute('aria-expanded', 'false');

    await clubButton.click();
    await navigation.getByRole('link', { name: 'Quiénes somos' }).click();
    await expect(page).toHaveURL(/\/club\/quienes-somos$/);
    await expect(page.getByRole('heading', { name: 'Quiénes somos E2E', level: 1 }))
      .toBeVisible();
    await expect(clubButton).toHaveClass(/navItemActive/);
  });

  test('móvil navega a Escuela, cierra todo y aplica Escape en dos niveles', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const openMenu = page.getByRole('button', { name: 'Abrir menú de navegación' });
    await openMenu.click();
    const learnButton = page.getByRole('button', { name: 'Aprende' });
    await learnButton.click();
    await page.getByRole('link', { name: 'Escuela de Galotxas' }).click();

    await expect(page).toHaveURL(/\/escuela$/);
    await expect(page.getByRole('heading', { name: 'Escuela de Galotxas', level: 1 }))
      .toBeVisible();
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('link', {
      name: 'Escuela de Galotxas',
      includeHidden: true,
    })).toBeHidden();

    await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
    await learnButton.click();
    await page.keyboard.press('Escape');
    await expect(learnButton).toBeFocused();
    await expect(page.getByRole('button', { name: 'Cerrar menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' })).toBeFocused();
  });

  test('Home ofrece sólo recorridos reales y el footer global usa rutas y redes confirmadas', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Galotxas en Monóvar', level: 1 })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ver competición' }).first())
      .toHaveAttribute('href', '/competicion');
    await expect(page.getByRole('link', { name: 'Aprender a jugar' }))
      .toHaveAttribute('href', '/aprende-a-jugar');
    await expect(page.getByText(/plataforma oficial|Prensa & Media|Federaciones/i)).toHaveCount(0);

    const footer = page.getByRole('contentinfo');
    const clubLinks = footer.getByRole('navigation', { name: 'Enlaces del Club' });
    for (const [name, href] of [
      ['Quiénes somos', '/club/quienes-somos'],
      ['Contacto', '/club/contacto'],
      ['Federarse', '/club/federarse'],
      ['Documentos', '/club/documentos'],
    ]) {
      await expect(clubLinks.getByRole('link', { name })).toHaveAttribute('href', href);
    }

    const socialLinks = footer.getByRole('navigation', { name: 'Redes sociales' });
    for (const link of [
      socialLinks.getByRole('link', { name: /Facebook/ }),
      socialLinks.getByRole('link', { name: /Instagram/ }),
    ]) {
      await expect(link).toHaveAttribute('target', '_blank');
      await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    }

    await expect(footer.getByText(/Club Galotxes Monòver/).first()).toBeVisible();
    await expect(footer.getByText(new RegExp(`© ${new Date().getFullYear()}`))).toBeVisible();
    await expect(footer.getByText(/Privacidad|Aviso legal|Cookies|Prensa|Federaciones/))
      .toHaveCount(0);

    await page.goto('/login');
    await expect(page.getByRole('contentinfo')).toBeVisible();
  });

  test('Cuenta conserva los estados anónimo y autenticado fuera de la navegación editorial', async ({ page }) => {
    await page.goto('/');
    const account = page.getByRole('group', { name: 'Cuenta' });
    const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });

    await expect(account.getByRole('link', { name: 'Iniciar sesión' })).toBeVisible();
    await expect(editorialNavigation.getByRole('link', { name: 'Iniciar sesión' })).toHaveCount(0);

    await page.goto('/login');
    await page.getByLabel('Correo Electrónico').fill(playerCredentials.email);
    await page.getByLabel('Contraseña').fill(playerCredentials.password);
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await expect(page).toHaveURL(/\/player$/);

    await expect(account.getByRole('link', { name: 'Mi Panel' })).toBeVisible();
    await expect(account.getByRole('button', { name: 'Salir' })).toBeVisible();
    await expect(editorialNavigation.getByRole('link', { name: 'Mi Panel' })).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => localStorage.getItem('user'))).toBeNull();
  });

  test('no carga recursos remotos conocidos y mantiene las rutas legales sin publicar', async ({ page }) => {
    const remoteRequests = [];
    page.on('request', (request) => {
      if (/fonts\.(googleapis|gstatic)|fonts\.bunny|cdn\.jsdelivr/.test(request.url())) {
        remoteRequests.push(request.url());
      }
    });

    for (const pathname of ['/', '/competicion', '/club/quienes-somos', '/login']) {
      await page.goto(pathname);
      await expect(page.locator('main')).toBeVisible();
      await expect(page.locator('iframe')).toHaveCount(0);
    }

    expect(remoteRequests).toEqual([]);

    for (const pathname of ['/aviso-legal', '/privacidad', '/cookies']) {
      await page.goto(pathname);
      await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
        .toBeVisible();
    }
  });

  test('Navbar, Home y footer no provocan overflow crítico a 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 740 });
    await page.goto('/');
    await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
    await page.getByRole('button', { name: 'Club' }).click();

    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
    await expect(page.getByRole('contentinfo')).toBeVisible();
  });
});
