import { expect, test } from '@playwright/test';

const backendBaseURL = process.env.E2E_BACKEND_URL || 'http://127.0.0.1:8081';
const password = 'E2E-password-123!';

const users = {
  admin: 'admin.e2e@example.test',
  first: 'cup-player1.e2e@example.test',
  second: 'cup-player2.e2e@example.test',
  third: 'cup-player3.e2e@example.test',
  fourth: 'cup-player4.e2e@example.test',
};

const names = {
  first: 'Pilotari Copa E2E 1',
  second: 'Pilotari Copa E2E 2',
  third: 'Pilotari Copa E2E 3',
  fourth: 'Pilotari Copa E2E 4',
};

const loginPlayer = async (page, email) => {
  await page.goto('/login');
  await page.getByLabel('Correo Electrónico').fill(email);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
  await expect(page).toHaveURL(/\/player$/);
};

const logoutPlayer = async (page) => {
  await page.getByRole('button', { name: 'Salir' }).click();
  await expect(
    page.getByRole('group', { name: 'Cuenta' }).getByRole('link', { name: 'Iniciar sesión' }),
  ).toBeVisible();
};

const loginAdmin = async (page) => {
  await page.goto(`${backendBaseURL}/admin`);

  if (/\/admin$/.test(page.url())) {
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    return;
  }

  await expect(page).toHaveURL(/\/admin\/login$/);
  await page.getByLabel('Email').fill(users.admin);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
};

const matchRow = (page, roundName, rowIndex = 0) => page
  .getByRole('heading', { name: roundName, exact: true })
  .locator('..')
  .locator('tbody tr')
  .nth(rowIndex);

const saveAdminMatch = async (page, categoryId, roundName, {
  status,
  homeScore = null,
  awayScore = null,
  date,
  time,
  rowIndex = 0,
} = {}) => {
  await page.goto(`${backendBaseURL}/admin/categories/${categoryId}`);
  const row = matchRow(page, roundName, rowIndex);

  await expect(row).toBeVisible();
  if (date) await row.locator('input[name="scheduled_date"]').fill(date);
  if (time) await row.locator('input[name="scheduled_time"]').fill(time);
  await row.locator('select[name="venue_id"]').selectOption({ label: 'Pista E2E' });
  await row.locator('select[name="status"]').selectOption(status);
  await row.locator('input[name="home_score"]').fill(homeScore === null ? '' : String(homeScore));
  await row.locator('input[name="away_score"]').fill(awayScore === null ? '' : String(awayScore));
  await row.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.locator('.alert-success')).toContainText('Partido actualizado correctamente.');
};

const getCupFixture = async (request) => {
  const loginResponse = await request.post(`${backendBaseURL}/api/v1/auth/login`, {
    data: { email: users.admin, password },
  });
  expect(loginResponse.ok()).toBe(true);
  const token = (await loginResponse.json()).data.token;
  const headers = { Authorization: `Bearer ${token}` };

  const championshipsResponse = await request.get(`${backendBaseURL}/api/v1/admin/championships`, {
    headers,
  });
  expect(championshipsResponse.ok()).toBe(true);
  const championships = (await championshipsResponse.json()).data;
  const championship = championships.find(({ name }) => name === 'Campeonato Copa E2E');
  expect(championship).toBeTruthy();

  const categoriesResponse = await request.get(`${backendBaseURL}/api/v1/admin/categories`, {
    headers,
  });
  expect(categoriesResponse.ok()).toBe(true);
  const category = (await categoriesResponse.json()).data
    .find(({ name }) => name === 'Copa E2E');
  expect(category).toBeTruthy();

  return { championship, category, headers };
};

const setCupVisibility = async (request, fixture, isPublic) => {
  const championshipPayload = {
    season_id: fixture.championship.season_id,
    name: fixture.championship.name,
    description: fixture.championship.description,
    type: fixture.championship.type,
    status: fixture.championship.status,
    is_public: isPublic,
    start_date: fixture.championship.start_date,
    end_date: fixture.championship.end_date,
    registration_status: fixture.championship.registration_status,
    registration_starts_at: fixture.championship.registration_starts_at,
    registration_ends_at: fixture.championship.registration_ends_at,
  };
  const categoryPayload = {
    name: fixture.category.name,
    description: fixture.category.description,
    level: fixture.category.level,
    gender: fixture.category.gender,
    status: fixture.category.status,
    is_public: isPublic,
  };

  if (isPublic) {
    expect((await request.patch(
      `${backendBaseURL}/api/v1/admin/championships/${fixture.championship.id}`,
      { headers: fixture.headers, data: championshipPayload },
    )).ok()).toBe(true);
    expect((await request.patch(
      `${backendBaseURL}/api/v1/admin/categories/${fixture.category.id}`,
      { headers: fixture.headers, data: categoryPayload },
    )).ok()).toBe(true);
    return;
  }

  expect((await request.patch(
    `${backendBaseURL}/api/v1/admin/categories/${fixture.category.id}`,
    { headers: fixture.headers, data: categoryPayload },
  )).ok()).toBe(true);
  expect((await request.patch(
    `${backendBaseURL}/api/v1/admin/championships/${fixture.championship.id}`,
    { headers: fixture.headers, data: championshipPayload },
  )).ok()).toBe(true);
};

const getSchedule = async (request, categoryId) => {
  const response = await request.get(
    `${backendBaseURL}/api/v1/categories/${categoryId}/schedule`,
  );
  expect(response.ok()).toBe(true);

  return (await response.json()).data;
};

const fillResult = async (page, homeName, awayName, homeScore, awayScore) => {
  await page.getByRole('spinbutton', { name: homeName }).fill(String(homeScore));
  await page.getByRole('spinbutton', { name: awayName }).fill(String(awayScore));
};

test('completa Liga, semifinales, conflicto, finales y campeón público de Copa', async ({ page, request }) => {
  test.setTimeout(180_000);

  const fixture = await getCupFixture(request);
  const { category } = fixture;
  const categoryAdminURL = `${backendBaseURL}/admin/categories/${category.id}`;
  const leagueRounds = Array.from({ length: 6 }, (_, index) => `Liga Copa E2E ${index + 1}`);

  await setCupVisibility(request, fixture, true);
  await loginAdmin(page);

  try {
    for (const roundName of leagueRounds) {
      await saveAdminMatch(page, category.id, roundName, {
        status: 'validated',
        homeScore: 10,
        awayScore: 0,
      });
    }

    await page.goto(categoryAdminURL);
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Generar copa' }).click();
    await expect(page.locator('.alert-success')).toContainText('Semifinales de copa generadas correctamente.');

    let schedule = await getSchedule(request, category.id);
    let semifinalRound = schedule.find(({ stage }) => stage === 'semifinal');
    expect(semifinalRound.phase).toBe('cup');
    expect(semifinalRound.matches).toHaveLength(2);
    expect(semifinalRound.matches.map((match) => [
      match.home_entry.public_display_name,
      match.away_entry.public_display_name,
    ])).toEqual([
      [names.first, names.fourth],
      [names.second, names.third],
    ]);

    for (let index = 0; index < 2; index += 1) {
      await saveAdminMatch(page, category.id, 'Semifinales', {
        status: 'scheduled',
        date: `2026-09-${12 + index}`,
        time: index === 0 ? '18:30' : '20:00',
        rowIndex: index,
      });
    }

    schedule = await getSchedule(request, category.id);
    semifinalRound = schedule.find(({ stage }) => stage === 'semifinal');
    const [matchingSemifinal, conflictingSemifinal] = semifinalRound.matches;

    await loginPlayer(page, users.first);
    await page.goto(`/matches/${matchingSemifinal.id}`);
    await fillResult(page, names.first, names.fourth, 10, 7);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();
    await logoutPlayer(page);

    await loginPlayer(page, users.fourth);
    await page.goto(`/matches/${matchingSemifinal.id}`);
    await page.getByRole('button', { name: 'Confirmar este resultado' }).click();
    await expect(page.getByText('Resultado validado oficialmente.')).toBeVisible();
    await logoutPlayer(page);

    await loginPlayer(page, users.second);
    await page.goto(`/matches/${conflictingSemifinal.id}`);
    await fillResult(page, names.second, names.third, 10, 6);
    await page.getByRole('button', { name: 'Enviar resultado' }).click();
    await expect(page.getByText('Estado del flujo: Pendiente de confirmación')).toBeVisible();
    await logoutPlayer(page);

    await loginPlayer(page, users.third);
    await page.goto(`/matches/${conflictingSemifinal.id}`);
    await fillResult(page, names.second, names.third, 7, 10);
    await page.getByRole('button', { name: 'Enviar discrepancia' }).click();
    await expect(page.getByText('Estado del flujo: En revisión')).toBeVisible();

    await loginAdmin(page);
    await page.goto(`${backendBaseURL}/admin/match-conflicts`);
    const conflictRow = page.getByRole('row').filter({ hasText: 'Copa E2E' });
    await conflictRow.getByRole('link', { name: 'Revisar y resolver' }).click();
    await page.getByLabel('Tanteo oficial local').fill('10');
    await page.getByLabel('Tanteo oficial visitante').fill('8');
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar resolución' }).click();
    await expect(page.getByRole('alert')).toContainText('Conflicto resuelto y resultado validado correctamente.');

    await page.goto(categoryAdminURL);
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Generar finales' }).click();
    await expect(page.locator('.alert-success')).toContainText('Final y 3º/4º generados correctamente.');

    schedule = await getSchedule(request, category.id);
    const finalRound = schedule.find(({ stage }) => stage === 'final');
    const thirdPlaceRound = schedule.find(({ stage }) => stage === 'third_place');
    expect(finalRound.matches).toHaveLength(1);
    expect(thirdPlaceRound.matches).toHaveLength(1);
    expect([
      finalRound.matches[0].home_entry.public_display_name,
      finalRound.matches[0].away_entry.public_display_name,
    ]).toEqual([names.first, names.second]);
    expect([
      thirdPlaceRound.matches[0].home_entry.public_display_name,
      thirdPlaceRound.matches[0].away_entry.public_display_name,
    ]).toEqual([names.fourth, names.third]);

    await saveAdminMatch(page, category.id, 'Final', {
      status: 'validated',
      date: '2026-09-20',
      time: '19:00',
      homeScore: 10,
      awayScore: 6,
    });
    await saveAdminMatch(page, category.id, '3º y 4º', {
      status: 'validated',
      date: '2026-09-20',
      time: '17:00',
      homeScore: 10,
      awayScore: 8,
    });

    schedule = await getSchedule(request, category.id);
    const publicFinal = schedule.find(({ stage }) => stage === 'final').matches[0];
    expect(publicFinal.winner_entry).toEqual({
      entry_type: 'player',
      public_display_name: names.first,
    });
    expect(publicFinal).not.toHaveProperty('winner_entry_id');
    expect(publicFinal).not.toHaveProperty('validated_by');

    await page.goto(`/categories/${category.id}/schedule`);
    await expect(page.getByRole('heading', { name: 'Copa', exact: true, level: 2 })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Semifinales', exact: true, level: 3 })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Final', exact: true, level: 3 })).toBeVisible();
    await expect(page.getByRole('heading', {
      name: 'Tercer y cuarto puesto',
      exact: true,
      level: 3,
    })).toBeVisible();
    await expect(page.getByText('Campeón de Copa').locator('..')).toContainText(names.first);

    await page.setViewportSize({ width: 320, height: 900 });
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    )).toBe(true);
  } finally {
    await loginAdmin(page);
    await page.goto(categoryAdminURL);
    const deleteCupButton = page.getByRole('button', { name: 'Eliminar copa' });

    if (await deleteCupButton.count()) {
      page.once('dialog', (dialog) => dialog.accept());
      await deleteCupButton.click();
      await expect(page.locator('.alert-success')).toContainText('Copa eliminada correctamente.');
    }

    for (const roundName of leagueRounds) {
      await saveAdminMatch(page, category.id, roundName, { status: 'scheduled' });
    }
    await setCupVisibility(request, fixture, false);
  }
});
