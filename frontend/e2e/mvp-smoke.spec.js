import { expect, test } from '@playwright/test';

const adminBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';

const credentials = {
  admin: {
    email: 'admin.e2e@example.test',
    password: 'E2E-password-123!',
  },
  player1: {
    email: 'player1.e2e@example.test',
    password: 'E2E-password-123!',
  },
  player2: {
    email: 'player2.e2e@example.test',
    password: 'E2E-password-123!',
  },
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

const login = async (page, user) => {
  await page.goto('/login');
  await page.getByLabel('Correo Electrónico').fill(user.email);
  await page.getByLabel('Contraseña').fill(user.password);
  await page.getByRole('button', { name: 'Iniciar Sesión' }).click();

  await expect(page).toHaveURL(/\/player$/);
  await expect(page.getByRole('heading', { name: 'Panel de Control' })).toBeVisible();
};

const logout = async (page) => {
  await page.getByRole('button', { name: 'Salir' }).click();
  await expect(
    page.getByRole('group', { name: 'Cuenta' }).getByRole('link', { name: 'Iniciar sesión' }),
  ).toBeVisible();
};

const fillScore = async (page, homeScore, awayScore) => {
  await page.getByRole('spinbutton', { name: 'Pilotari E2E 1' }).fill(String(homeScore));
  await page.getByRole('spinbutton', { name: 'Pilotari E2E 2' }).fill(String(awayScore));
};

test.describe.serial('smoke narrativo del MVP', () => {
  let confirmationMatchPath;
  let reviewMatchPath;
  let publicChampionshipPath;
  let publicCategoryPath;
  let publicStandingsPath;
  let publicSchedulePath;
  let publicMatchPath;

  test('la navegación pública de escritorio conecta Inicio, Competición y sus destinos', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'La emoción de las Galotxas' })).toBeVisible();
    const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });
    const accountArea = page.getByRole('group', { name: 'Cuenta' });

    await expect(editorialNavigation.getByRole('link')).toHaveCount(3);
    await expect(editorialNavigation.getByRole('link', { name: 'Inicio' })).toBeVisible();
    await expect(editorialNavigation.getByRole('link', { name: 'Competición' })).toBeVisible();
    await expect(editorialNavigation.getByRole('link', { name: 'Aprende a jugar' })).toBeVisible();
    await expect(editorialNavigation.getByRole('link', { name: 'Torneos' })).toHaveCount(0);
    await expect(editorialNavigation.getByRole('link', { name: 'Rankings' })).toHaveCount(0);
    await expect(accountArea.getByRole('link', { name: 'Iniciar sesión' })).toBeVisible();

    await editorialNavigation.getByRole('link', { name: 'Competición' }).click();
    await expect(page).toHaveURL(/\/competicion$/);
    await expect(page.getByRole('heading', { name: 'Competición', level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Temporadas y campeonatos', level: 2 }))
      .toBeVisible();
    await expect(page.getByRole('heading', { name: 'Temporada E2E 2026', level: 3 }))
      .toBeVisible();
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E', level: 4 }))
      .toBeVisible();
    await expect(page.getByText('Activa', { exact: true })).toBeVisible();
    await expect(page.getByText('Activo', { exact: true })).toBeVisible();
    await expect(page.getByText('1/1/2026', { exact: true })).toBeVisible();
    await expect(page.getByText('31/12/2026', { exact: true })).toBeVisible();
    await expect(page.getByText('is_public', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Ranking histórico', level: 2 })).toBeVisible();
    await expect(page.getByText('Todavía no hay datos disponibles en el ranking histórico.'))
      .toBeVisible();
    await expect(page.getByRole('link', { name: 'Ver ranking completo' }))
      .toHaveAttribute('href', '/rankings');
    await expect(page.getByRole('heading', { name: 'Explora los campeonatos', level: 2 })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Acceso principal de Competición' }).getByRole('link'))
      .toHaveCount(1);
    await expect(page).toHaveTitle('Competición | Galotxas');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta temporadas y campeonatos públicos, calendarios, resultados y clasificaciones de Galotxas.',
    );

    await page.getByRole('link', { name: 'Ver detalle de Campeonato Individual E2E' }).click();
    await expect(page).toHaveURL(/\/torneos\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E', level: 1 }))
      .toBeVisible();

    await editorialNavigation.getByRole('link', { name: 'Competición' }).click();
    await page.getByRole('link', { name: /Torneos/ }).click();
    await expect(page).toHaveURL(/\/torneos$/);
    await expect(page.getByRole('heading', { name: 'Torneos', level: 1 })).toBeVisible();

    await editorialNavigation.getByRole('link', { name: 'Competición' }).click();
    await page.getByRole('link', { name: 'Ver ranking completo' }).click();
    await expect(page).toHaveURL(/\/rankings$/);
    await expect(page.getByRole('heading', { name: 'Rankings de Galotxas', level: 1 })).toBeVisible();

    assertNoConsoleErrors();
  });

  test('las rutas de cuenta y la página CMS publicada siguen accesibles', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/register');
    const playerToggle = page.getByRole('checkbox', { name: 'Soy jugador' });
    await expect(playerToggle).not.toBeChecked();
    await playerToggle.check();
    await expect(page.getByLabel('Apodo (Nickname)')).toBeVisible();
    await expect(page.getByLabel('Nivel de juego (1-10) *')).toBeVisible();

    await page.goto('/forgot-password');
    await expect(page.getByLabel('Correo Electrónico')).toBeVisible();

    await page.goto('/torneos');
    await expect(page.getByRole('heading', { name: 'Torneos', level: 1 })).toBeVisible();
    await expect(page.getByText(/En construcción/)).toHaveCount(0);

    await page.goto('/contenidos');
    await expect(page).toHaveURL(/\/contenidos$/);
    await expect(page.getByRole('heading', { name: 'Contenidos' })).toBeVisible();

    const publishedPage = page.getByRole('article').filter({ hasText: 'Contenido E2E publicado' });
    await expect(publishedPage).toBeVisible();
    await publishedPage.getByRole('link', { name: 'Ver contenido' }).click();

    await expect(page).toHaveURL(/\/contenidos\/e2e-publicada$/);
    await expect(page.getByRole('heading', { name: 'Contenido E2E publicado' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Escenario público E2E' })).toBeVisible();
    await expect(page.getByText('Este contenido procede exclusivamente de la base temporal E2E.')).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('Competición permanece activa en la landing y sus destinos secundarios', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    for (const [pathname, currentValue] of [
      ['/competicion', 'page'],
      ['/torneos', 'location'],
      ['/rankings', 'location'],
    ]) {
      await page.goto(pathname);
      const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });
      const competitionLink = editorialNavigation.getByRole('link', { name: 'Competición' });

      await expect(competitionLink).toHaveAttribute('aria-current', currentValue);
      await expect(editorialNavigation.locator('[aria-current]')).toHaveCount(1);
    }

    assertNoConsoleErrors();
  });

  test('Aprende conecta landing, Manual, documentos, referencias y 404 segura', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    const editorialNavigation = page.getByRole('list', { name: 'Navegación editorial' });
    const learnNavigationLink = editorialNavigation.getByRole('link', { name: 'Aprende a jugar' });

    await learnNavigationLink.click();
    await expect(page).toHaveURL(/\/aprende-a-jugar$/);
    await expect(page.getByRole('heading', { name: 'Aprende a jugar', level: 1 })).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(learnNavigationLink).toHaveAttribute('aria-current', 'page');
    await expect(editorialNavigation.getByRole('link', { name: 'Competición' }))
      .not.toHaveAttribute('aria-current');
    await expect(page).toHaveTitle('Aprende a jugar | Galotxas');

    const manualAccess = page.getByRole('link', { name: 'Consultar el Manual' });
    await manualAccess.focus();
    await expect(manualAccess).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/aprende-a-jugar\/manual$/);
    await expect(page.getByRole('heading', { name: 'Manual', level: 1 })).toBeVisible();
    await expect(page.locator('main a[href*="/aprende-a-jugar/manual/"]')).toHaveCount(40);
    await expect(learnNavigationLink).toHaveAttribute('aria-current', 'location');

    const regulationLink = page.getByRole('link', { name: 'El saque', exact: true });
    await regulationLink.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/aprende-a-jugar\/manual\/reglamento\/saque$/);
    await expect(page.getByRole('heading', { name: 'El saque', level: 1 })).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.getByText('REG-003', { exact: true })).toBeVisible();

    const realReference = page.getByRole('link', { name: 'REG-002 – Reglamento' }).first();
    await realReference.focus();
    await expect(realReference).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/aprende-a-jugar\/manual\/reglamento\/reglamento$/);
    await expect(page.getByRole('heading', { name: 'Reglamento', level: 1 })).toBeVisible();

    await page.getByRole('link', { name: '← Volver al Manual' }).first().click();
    await page.getByRole('link', { name: 'Pilota', exact: true }).click();
    await expect(page).toHaveURL(/\/aprende-a-jugar\/manual\/conceptos\/elementos\/pilota$/);
    await expect(page.getByRole('heading', { name: 'Pilota', level: 1 })).toBeVisible();
    await expect(page.getByText(/pilota/).first()).toBeVisible();

    await page.getByRole('link', { name: '← Volver al Manual' }).first().click();
    await page.getByRole('link', { name: 'Sistema de puntuación', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Sistema de puntuación', level: 1 })).toBeVisible();
    const scoreTable = page.getByRole('table');
    const tableRegion = page.getByRole('region', { name: 'Tabla con desplazamiento horizontal' });
    await expect(scoreTable.getByRole('columnheader', { name: 'Puntuación' })).toBeVisible();
    await expect(scoreTable.getByRole('cell', { name: 'Quinse' })).toBeVisible();

    for (const width of [320, 375, 768, 1024, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      )).toBe(true);
      await expect(tableRegion).toBeVisible();
      expect(await tableRegion.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return rect.left >= 0 && rect.right <= document.documentElement.clientWidth + 0.5;
      })).toBe(true);
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

    for (const pathname of [
      '/aprende-a-jugar/manual/reglamento/inexistente',
      '/aprende-a-jugar/manual/conceptos/instalaciones/cancha',
    ]) {
      await page.goto(pathname);
      await expect(page).toHaveURL(new RegExp(`${pathname}$`));
      await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 })).toBeVisible();
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex');
      await expect(page.getByText(/Borrador|sourcePath/)).toHaveCount(0);
    }

    assertNoConsoleErrors();
  });

  test('la landing común de Competición es responsive y navegable por teclado', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/competicion');
    const season = page.getByRole('region', { name: 'Temporada E2E 2026' });
    const championship = page.getByRole('article', { name: 'Campeonato Individual E2E' });

    await expect(season).toBeVisible();
    await expect(championship).toBeVisible();

    for (const width of [320, 375, 768, 1024, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });

      const destinations = page.getByRole('navigation', { name: 'Acceso principal de Competición' });
      const destinationLinks = destinations.getByRole('link');

      await expect(destinationLinks).toHaveCount(1);
      await expect(destinations.getByRole('link', { name: /Torneos/ })).toBeVisible();

      const layoutState = await destinationLinks.evaluateAll((links) => ({
        cardsAreLegible: links.every((link) => {
          const rect = link.getBoundingClientRect();

          return rect.width > 0
            && rect.height >= 44
            && rect.left >= 0
            && rect.right <= document.documentElement.clientWidth + 0.5
            && link.scrollWidth <= link.clientWidth;
        }),
        hasHorizontalOverflow:
          document.documentElement.scrollWidth > document.documentElement.clientWidth,
      }));

      expect(layoutState, `Landing de Competición a ${width}px`).toEqual({
        cardsAreLegible: true,
        hasHorizontalOverflow: false,
      });

      const dynamicBlocksAreLegible = await Promise.all(
        [season, championship].map((block) => block.evaluate((element) => {
          const rect = element.getBoundingClientRect();

          return rect.width > 0
            && rect.left >= 0
            && rect.right <= document.documentElement.clientWidth + 0.5
            && element.scrollWidth <= element.clientWidth;
        })),
      );

      expect(dynamicBlocksAreLegible, `Jerarquía dinámica a ${width}px`).toEqual([true, true]);
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    const accountLink = page
      .getByRole('group', { name: 'Cuenta' })
      .getByRole('link', { name: 'Iniciar sesión' });
    const primaryTournamentLink = page
      .getByRole('navigation', { name: 'Acceso principal de Competición' })
      .getByRole('link', { name: /Torneos/ });
    const championshipLink = page
      .getByRole('link', { name: 'Ver detalle de Campeonato Individual E2E' });

    await accountLink.focus();
    await page.keyboard.press('Tab');
    await expect(primaryTournamentLink).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(championshipLink).toBeFocused();
    await expect(championshipLink.locator('a, button, input, select, textarea')).toHaveCount(0);

    const focusStyle = await championshipLink.evaluate((link) => {
      const style = getComputedStyle(link);

      return { outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth };
    });

    expect(focusStyle).toEqual({ outlineStyle: 'solid', outlineWidth: '3px' });
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/torneos\/\d+$/);

    assertNoConsoleErrors();
  });

  test('el CTA principal abre el listado real de torneos', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await page.getByRole('link', { name: 'Ver Torneos' }).click();

    await expect(page).toHaveURL(/\/torneos$/);
    await expect(page.getByRole('heading', { name: 'Torneos' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E' })).toBeVisible();

    await page.getByLabel('Estado').selectOption('finished');
    await expect(page.getByText('No hay campeonatos para los filtros seleccionados.')).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('el recorrido público conecta Inicio, campeonato, categoría, clasificación, calendario y partido', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/');
    await page.getByRole('list', { name: 'Navegación editorial' })
      .getByRole('link', { name: 'Competición' }).click();
    await page.getByRole('link', { name: 'Ver detalle de Campeonato Individual E2E' }).click();
    await expect(page.getByRole('heading', { name: 'Campeonato Individual E2E' })).toBeVisible();
    publicChampionshipPath = new URL(page.url()).pathname;
    await expect(page.getByRole('link', { name: 'Clasificación' }))
      .toHaveAttribute('href', /\/categories\/\d+\/standings$/);
    await expect(page.getByRole('link', { name: 'Calendario y resultados' }))
      .toHaveAttribute('href', /\/categories\/\d+\/schedule$/);
    await page.getByRole('link', { name: 'Ver categoría' }).click();
    await expect(page.getByRole('heading', { name: 'Individual E2E', exact: true })).toBeVisible();
    publicCategoryPath = new URL(page.url()).pathname;
    await expect(page.getByRole('link', { name: 'Resumen' })).toHaveAttribute('aria-current', 'page');

    await page.getByRole('link', { name: 'Clasificación' }).click();
    await expect(page).toHaveURL(/\/categories\/\d+\/standings$/);
    publicStandingsPath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Clasificación de Individual E2E' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Clasificación' })).toHaveAttribute('aria-current', 'page');
    await page.getByRole('link', { name: 'Calendario y resultados' }).click();

    await expect(page).toHaveURL(/\/categories\/\d+\/schedule$/);
    publicSchedulePath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Calendario y resultados de Individual E2E' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Calendario y resultados' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('heading', { name: 'Jornada E2E Confirmación' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Jornada E2E Discrepancia' })).toBeVisible();
    await expect(page.getByText('Pilotari E2E 1')).toHaveCount(2);
    await expect(page.getByText('Pilotari E2E 2')).toHaveCount(2);
    await expect(page.getByText('Pista: Pista E2E')).toHaveCount(2);
    await expect(page.getByText('No hay jornadas configuradas todavía.')).toHaveCount(0);

    await page.getByRole('link', { name: 'Ver partido: Pilotari E2E 1 contra Pilotari E2E 2' }).first().click();
    await expect(page).toHaveURL(/\/matches\/\d+$/);
    publicMatchPath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Detalles de la partida' })).toBeVisible();
    await expect(page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2')).toBeVisible();
    await expect(page.getByRole('link', { name: '← Volver al calendario de la categoría' }))
      .toHaveAttribute('href', publicSchedulePath);

    assertNoConsoleErrors();
  });

  test('la rama deportiva completa evita overflow documental y conserva foco visible', async ({ page }) => {
    const paths = [
      '/competicion',
      '/torneos',
      publicChampionshipPath,
      publicCategoryPath,
      publicStandingsPath,
      publicSchedulePath,
      publicMatchPath,
      '/rankings',
    ];

    for (const pathname of paths) {
      for (const width of [320, 375, 768, 1024, 1280, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(pathname);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect.poll(() => page.evaluate(
          () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
        )).toBe(true);
      }
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(publicCategoryPath);
    const standingsLink = page.getByRole('link', { name: 'Clasificación' });
    await standingsLink.focus();
    await expect(standingsLink).toBeFocused();
    const focusStyle = await standingsLink.evaluate((link) => getComputedStyle(link).outlineStyle);
    expect(focusStyle).toBe('solid');

    await page.evaluate(() => {
      document.body.style.zoom = '200%';
    });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
  });

  test('la navegación móvil permite recorrer enlaces públicos sin desbordamiento', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/');
    const openMenu = page.getByRole('button', { name: 'Abrir menú de navegación' });
    await expect(openMenu).toBeVisible();
    await expect(openMenu).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('link', { name: 'Competición', exact: true })).toBeHidden();

    await openMenu.click();
    const closeMenu = page.getByRole('button', { name: 'Cerrar menú de navegación' });
    await expect(closeMenu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('link', { name: 'Inicio', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Competición', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Aprende a jugar', exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'Competición', exact: true }).click();
    await expect(page).toHaveURL(/\/competicion$/);
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('link', { name: 'Competición', exact: true })).toBeHidden();

    await page.getByRole('button', { name: 'Abrir menú de navegación' }).click();
    await page.getByRole('link', { name: 'Competición', exact: true }).focus();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Abrir menú de navegación' })).toBeFocused();
    await expect(page.getByRole('link', { name: 'Competición', exact: true })).toBeHidden();
    await page.keyboard.press('Tab');
    await expect(
      page.getByRole('group', { name: 'Cuenta' }).getByRole('link', { name: 'Iniciar sesión' }),
    ).toBeFocused();

    const hasNoHorizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    );
    expect(hasNoHorizontalOverflow).toBe(true);

    assertNoConsoleErrors();
  });

  test('el Navbar evita overflow y solapamientos en la matriz responsive', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.setItem('token', 'e2e-responsive-token');
      localStorage.setItem('user', JSON.stringify({
        name: 'Nombre de participante deliberadamente muy largo para responsive',
      }));
    });
    await page.reload();

    for (const width of [320, 375, 768, 1024, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect(page.getByRole('navigation', { name: 'Navegación principal' })).toBeVisible();

      const layoutState = await page.evaluate(() => {
        const navbar = document.querySelector('nav[aria-label="Navegación principal"]');
        const visibleChildren = Array.from(navbar.children)
          .map((element) => ({
            display: getComputedStyle(element).display,
            rect: element.getBoundingClientRect(),
          }))
          .filter(({ display, rect }) => display !== 'none' && rect.width > 0 && rect.height > 0);

        const childrenOverlap = visibleChildren.some((child, index) => (
          visibleChildren.slice(index + 1).some((otherChild) => (
            child.rect.left < otherChild.rect.right
            && child.rect.right > otherChild.rect.left
            && child.rect.top < otherChild.rect.bottom
            && child.rect.bottom > otherChild.rect.top
          ))
        ));

        return {
          hasHorizontalOverflow:
            document.documentElement.scrollWidth > document.documentElement.clientWidth,
          childrenOverlap,
        };
      });

      expect(layoutState, `Estado responsive a ${width}px`).toEqual({
        hasHorizontalOverflow: false,
        childrenOverlap: false,
      });
    }
  });

  test('una URL desconocida muestra la 404 y permite volver a Inicio', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/cuenta/ruta-inexistente');
    await expect(page).toHaveURL(/\/cuenta\/ruta-inexistente$/);
    await expect(page.getByRole('heading', { name: 'Página no encontrada', level: 1 })).toBeVisible();
    await expect(page).toHaveTitle('Página no encontrada | Galotxas');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex');

    await page.getByRole('link', { name: 'Volver a Inicio' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { name: 'La emoción de las Galotxas' })).toBeVisible();
    await expect(page.locator('meta[name="robots"]')).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('login real, Mi Panel y acceso a una acción pendiente', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await expect(page.getByRole('heading', { name: 'Datos de Usuario' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Perfil de Jugador' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Acciones pendientes' })).toBeVisible();
    await expect(page.getByLabel('2 acciones pendientes')).toBeVisible();

    await page.getByRole('link', { name: 'Enviar resultado' }).first().click();
    await expect(page).toHaveURL(/\/matches\/\d+$/);
    confirmationMatchPath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Gestión del resultado' })).toBeVisible();

    assertNoConsoleErrors();
  });

  test('resultado coincidente, validación oficial y desaparición de la acción', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await page.goto(confirmationMatchPath);
    await expect(page.getByRole('heading', { name: 'Enviar resultado' })).toBeVisible();
    await fillScore(page, 10, 7);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();

    await logout(page);
    await login(page, credentials.player2);
    await page.getByRole('link', { name: 'Confirmar resultado' }).click();
    await expect(page).toHaveURL(confirmationMatchPath);
    await page.getByRole('button', { name: 'Confirmar este resultado' }).click();

    await expect(page.getByText('Resultado validado oficialmente.')).toBeVisible();
    const officialScore = page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2');
    await expect(officialScore).toContainText('10');
    await expect(officialScore).toContainText('7');

    await page.goto('/player');
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Confirmar resultado' })).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('resultado discrepante queda en revisión sin formulario editable', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await login(page, credentials.player1);
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await page.getByRole('link', { name: 'Enviar resultado' }).click();
    await fillScore(page, 10, 6);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();

    await logout(page);
    await login(page, credentials.player2);
    await page.getByRole('link', { name: 'Confirmar resultado' }).click();
    reviewMatchPath = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: 'Reportar una discrepancia' })).toBeVisible();
    await fillScore(page, 7, 10);
    await page.getByRole('button', { name: 'Enviar discrepancia' }).click();

    await expect(page.getByText('Estado del flujo: En revisión')).toBeVisible();
    await expect(page.getByText(/Hay una discrepancia entre reportes/)).toBeVisible();

    await page.goto('/player');
    await expect(page.getByLabel('1 acciones pendientes')).toBeVisible();
    await expect(page.getByText('Resultado en revisión')).toBeVisible();
    await page.getByRole('link', { name: 'Ver revisión' }).click();
    await expect(page.getByText(/Hay una discrepancia entre reportes/)).toBeVisible();
    await expect(page.getByRole('spinbutton')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Enviar resultado|Enviar discrepancia|Confirmar este resultado/ })).toHaveCount(0);

    assertNoConsoleErrors();
  });

  test('el administrador resuelve la discrepancia desde el panel Blade', async ({ page }) => {
    await page.goto(`${adminBaseURL}/admin/login`);
    await page.getByLabel('Email').fill(credentials.admin.email);
    await page.getByLabel('Contraseña').fill(credentials.admin.password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    const adminMenuToggle = page.getByRole('button', { name: 'Abrir menú de administración' });
    await expect(adminMenuToggle).toHaveAttribute('aria-expanded', 'false');
    await adminMenuToggle.click();
    await expect(adminMenuToggle).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('link', { name: 'Conflictos', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Conflictos de resultados' })).toBeVisible();
    await expect(page.getByRole('cell', { name: '10 - 6' })).toBeVisible();
    await expect(page.getByRole('cell', { name: '7 - 10' })).toBeVisible();
    await page.getByRole('link', { name: 'Revisar y resolver' }).click();

    await expect(page.getByRole('heading', { name: 'Resolver conflicto de resultado' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Reporte local' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Reporte visitante' })).toBeVisible();
    await page.getByLabel('Tanteo oficial local').fill('10');
    await page.getByLabel('Tanteo oficial visitante').fill('8');

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar resolución' }).click();

    await expect(page).toHaveURL(/\/admin\/match-conflicts$/);
    await expect(page.getByRole('alert')).toContainText('Conflicto resuelto y resultado validado correctamente.');
    await expect(page.getByText('No hay conflictos de resultados pendientes de resolución.')).toBeVisible();

    await page.goto(reviewMatchPath);
    await expect(page.getByText('Finalizado', { exact: true })).toBeVisible();
    const officialScore = page.getByLabel('Pilotari E2E 1 contra Pilotari E2E 2');
    await expect(officialScore).toContainText('10');
    await expect(officialScore).toContainText('8');
  });

  test('el ranking refleja el resultado validado sin escala incorrecta ni NaN', async ({ page }) => {
    const assertNoConsoleErrors = watchCriticalConsoleErrors(page);

    await page.goto('/competicion');
    await expect(page.getByRole('heading', { name: 'Ranking histórico', level: 2 })).toBeVisible();
    const preview = page.getByRole('list', { name: 'Primeras posiciones del ranking histórico' });
    await expect(preview.getByText('Pilotari E2E 1')).toBeVisible();
    await expect(preview.getByRole('listitem')).toHaveCount(2);
    const fullRankingLink = page.getByRole('link', { name: 'Ver ranking completo' });

    for (const width of [320, 375, 768, 1024, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });

      const layoutState = await preview.evaluate((list) => ({
        entriesAreLegible: Array.from(list.children).every((entry) => {
          const rect = entry.getBoundingClientRect();

          return rect.width > 0
            && rect.left >= 0
            && rect.right <= document.documentElement.clientWidth + 0.5
            && entry.scrollWidth <= entry.clientWidth;
        }),
        hasHorizontalOverflow:
          document.documentElement.scrollWidth > document.documentElement.clientWidth,
      }));

      expect(layoutState, `Preview histórico a ${width}px`).toEqual({
        entriesAreLegible: true,
        hasHorizontalOverflow: false,
      });
      await expect(fullRankingLink).toBeVisible();
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await fullRankingLink.focus();
    await expect(fullRankingLink).toBeFocused();
    await fullRankingLink.click();

    await expect(page).toHaveURL(/\/rankings$/);
    await expect(page.getByRole('heading', { name: 'Rankings de Galotxas' })).toBeVisible();

    const winnerRow = page.getByRole('row').filter({ hasText: 'Pilotari E2E 1' });
    await expect(winnerRow).toBeVisible();
    await expect(winnerRow).toContainText('100,0%');
    await expect(page.getByText('NaN', { exact: true })).toHaveCount(0);

    assertNoConsoleErrors();
  });
});
