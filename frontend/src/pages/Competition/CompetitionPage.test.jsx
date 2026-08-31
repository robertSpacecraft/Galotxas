import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CompetitionPage } from './CompetitionPage';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getChampionships: vi.fn(),
    getAllTimeRanking: vi.fn(),
  },
}));

const championship = (id, overrides = {}) => ({
  id,
  name: `Campeonato ${id}`,
  slug: `campeonato-${id}`,
  type: 'singles',
  status: 'active',
  categories_count: 2,
  is_public: true,
  description: null,
  ...overrides,
});

const season = (id, status, overrides = {}) => ({
  id,
  name: `Temporada ${id}`,
  slug: null,
  status,
  start_date: null,
  end_date: null,
  is_public: true,
  championships: [],
  ...overrides,
});

const activeSeason = season(7, 'active', {
  name: 'Temporada 2026',
  start_date: '2026-01-01',
  end_date: '2026-12-31',
  championships: [championship(22, { name: 'Trofeu de Galotxas' })],
});

const renderPage = () => renderWithProviders(<CompetitionPage />, { route: '/competicion' });

const expectGlobalDestinations = () => {
  expect(screen.getByRole('link', { name: 'Ver todos los campeonatos' }))
    .toHaveAttribute('href', '/torneos');
  expect(screen.getByRole('link', { name: 'Ver ranking completo' }))
    .toHaveAttribute('href', '/rankings');
  expect(screen.getAllByRole('link').filter((link) => link.getAttribute('href') === '/rankings'))
    .toHaveLength(1);
};

describe('CompetitionPage', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getChampionships.mockReset();
    championshipsService.getAllTimeRanking.mockReset();
    championshipsService.getAllTimeRanking.mockResolvedValue([]);
  });

  it('makes one active season and its championships the primary content', async () => {
    championshipsService.getSeasons.mockResolvedValue([activeSeason]);

    const { container } = renderPage();

    expect(screen.getByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Temporada en curso', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toHaveAttribute('id', 'competition-season-7-title');
    expect(screen.getByRole('region', { name: 'Temporada 2026' })).toBeInTheDocument();
    expect(screen.getByRole('article', { name: 'Trofeu de Galotxas' })).toBeInTheDocument();
    expect(screen.getByText('Activa')).toBeInTheDocument();
    expect(screen.getByText('Activo')).toBeInTheDocument();
    expect(screen.getByText('Individual')).toBeInTheDocument();
    expect(screen.getByText('2 categorías')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver campeonato: Trofeu de Galotxas' }))
      .toHaveAttribute('href', '/torneos/22');
    expect(container).not.toHaveTextContent('Explora los campeonatos');
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expectGlobalDestinations();
    expect(championshipsService.getChampionships).not.toHaveBeenCalled();
    expect(document.title).toBe('Competición | Club Galotxes Monòver');
  });

  it('shows every active season without silently choosing one', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      activeSeason,
      season(8, 'active', {
        name: 'Temporada paralela',
        championships: [championship(23, { name: 'Campionat paral·lel' })],
      }),
    ]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporadas en curso', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Temporada 2026' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Temporada paralela' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver campeonato: Campionat paral·lel' }))
      .toHaveAttribute('href', '/torneos/23');
  });

  it('places planned seasons below active content and limits ordered history to three links', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      season(3, 'finished', { name: 'Temporada 2023', end_date: '2023-12-31' }),
      season(5, 'finished', { name: 'Temporada 2025', end_date: '2025-12-31' }),
      activeSeason,
      season(6, 'planned', { name: 'Temporada 2027' }),
      season(2, 'finished', { name: 'Temporada 2022', end_date: '2022-12-31' }),
      season(4, 'finished', { name: 'Temporada 2024', end_date: '2024-12-31' }),
    ]);

    renderPage();

    await screen.findByRole('heading', { name: 'Temporada en curso', level: 2 });
    expect(screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent))
      .toEqual([
        'Temporada en curso',
        'Próximamente',
        'Temporadas anteriores',
        'Ranking histórico',
      ]);
    expect(screen.getAllByRole('link', { name: /Ver campeonatos de Temporada 202/ }))
      .toHaveLength(3);
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2025' }))
      .toHaveAttribute('href', '/torneos?season_id=5');
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2024' }))
      .toHaveAttribute('href', '/torneos?season_id=4');
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2023' }))
      .toHaveAttribute('href', '/torneos?season_id=3');
    expect(screen.queryByText('Temporada 2022')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver todas las temporadas' }))
      .toHaveAttribute('href', '/torneos');
  });

  it('prioritizes a single planned season when there is no active season', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      season(6, 'planned', {
        name: 'Temporada 2027',
        championships: [championship(31, { name: 'Campionat futur' })],
      }),
    ]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Próxima temporada', level: 2 }))
      .toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /en curso/i })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver campeonato: Campionat futur' }))
      .toHaveAttribute('href', '/torneos/31');
    expectGlobalDestinations();
  });

  it('uses the latest finished season as a neutral reference when nothing is current or planned', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      season(4, 'finished', { name: 'Temporada 2024', end_date: '2024-12-31' }),
      season(5, 'finished', { name: 'Temporada 2025', end_date: '2025-12-31' }),
      season(3, 'finished', { name: 'Temporada 2023', end_date: '2023-12-31' }),
    ]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Última temporada disponible', level: 2 }))
      .toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /en curso/i })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2025' }))
      .toHaveAttribute('href', '/torneos?season_id=5');
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2024' }))
      .toHaveAttribute('href', '/torneos?season_id=4');
    expect(screen.getByRole('link', { name: 'Ver campeonatos de Temporada 2023' }))
      .toHaveAttribute('href', '/torneos?season_id=3');
  });

  it('keeps an active season visible when it has no championships', async () => {
    championshipsService.getSeasons.mockResolvedValue([{ ...activeSeason, championships: [] }]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByText('0 campeonatos')).toBeInTheDocument();
    expect(screen.getByText('Esta temporada no tiene campeonatos públicos disponibles.'))
      .toBeInTheDocument();
    expectGlobalDestinations();
  });

  it('renders six active championships as direct stable actions', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      { ...activeSeason, championships: Array.from({ length: 6 }, (_, index) => championship(index + 1)) },
    ]);

    renderPage();

    await screen.findByRole('heading', { name: 'Temporada en curso', level: 2 });
    expect(screen.getAllByRole('link', { name: /^Ver campeonato:/ })).toHaveLength(6);
    expect(screen.getByRole('link', { name: 'Ver campeonato: Campeonato 6' }))
      .toHaveAttribute('href', '/torneos/6');
  });

  it('degrades safely when only cancelled or unexpected statuses arrive', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      season(1, 'cancelled'),
      season(2, 'unexpected'),
      null,
    ]);

    renderPage();

    expect(await screen.findByText(/No hay temporadas en curso, próximas o finalizadas/))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Campeonatos', level: 2 })).toBeInTheDocument();
    expect(screen.queryByText('Temporada 1')).not.toBeInTheDocument();
    expect(screen.queryByText('Temporada 2')).not.toBeInTheDocument();
    expectGlobalDestinations();
  });

  it('announces loading and preserves the secondary destinations', () => {
    championshipsService.getSeasons.mockReturnValue(new Promise(() => {}));

    renderPage();

    expect(screen.getByText(/Cargando temporadas y campeonatos/)).toHaveAttribute('role', 'status');
    expectGlobalDestinations();
  });

  it('shows a global empty state without simulated seasons', async () => {
    championshipsService.getSeasons.mockResolvedValue([]);

    renderPage();

    expect(await screen.findByText('No hay temporadas disponibles en este momento.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 3 })).not.toBeInTheDocument();
    expectGlobalDestinations();
  });

  it('announces an error, preserves destinations and retries the real load', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons
      .mockRejectedValueOnce(new Error('Backend detail'))
      .mockResolvedValueOnce([activeSeason]);

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se han podido cargar las temporadas y campeonatos.',
    );
    expectGlobalDestinations();

    await user.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toBeInTheDocument();
    await waitFor(() => expect(screen.queryByRole('alert')).not.toBeInTheDocument());
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(2);
  });

  it('keeps the historical ranking preview order and maximum of five rows', async () => {
    championshipsService.getSeasons.mockResolvedValue([activeSeason]);
    championshipsService.getAllTimeRanking.mockResolvedValue([
      { position: 2, public_display_name: 'Segunda en respuesta', weighted_points: 20 },
      { position: 1, public_display_name: 'Primera en respuesta', weighted_points: 30 },
      { position: null, public_display_name: 'Provisional', weighted_points: 5 },
      { position: 4, public_display_name: 'Cuarta', weighted_points: 15 },
      { position: 5, public_display_name: 'Quinta', weighted_points: 10 },
      { position: 6, public_display_name: 'Sexta', weighted_points: 8 },
    ]);

    renderPage();

    const list = await screen.findByRole('list', {
      name: 'Primeras posiciones del ranking histórico',
    });
    expect(list.querySelectorAll('li')).toHaveLength(5);
    expect([...list.querySelectorAll('h3')].map((heading) => heading.textContent)).toEqual([
      'Segunda en respuesta',
      'Primera en respuesta',
      'Provisional',
      'Cuarta',
      'Quinta',
    ]);
    expect(screen.queryByText('Sexta')).not.toBeInTheDocument();
  });

  it('keeps ranking content usable when the independent season overview fails', async () => {
    championshipsService.getSeasons.mockRejectedValue(new Error('Seasons unavailable'));
    championshipsService.getAllTimeRanking.mockResolvedValue([
      { position: 1, public_display_name: 'Ranking disponible', weighted_points: 25 },
    ]);

    renderPage();

    expect(await screen.findByText('Ranking disponible')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'No se han podido cargar las temporadas y campeonatos.',
    );
    expect(screen.getByRole('link', { name: 'Ver ranking completo' })).toBeInTheDocument();
  });

  it('keeps active season content usable while the independent ranking is loading', async () => {
    championshipsService.getSeasons.mockResolvedValue([activeSeason]);
    championshipsService.getAllTimeRanking.mockReturnValue(new Promise(() => {}));

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByText(/Cargando ranking histórico/)).toHaveAttribute('role', 'status');
    expect(screen.getByRole('link', { name: 'Ver ranking completo' })).toBeInTheDocument();
  });

  it('retries only the independent ranking while keeping competition content usable', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons.mockResolvedValue([activeSeason]);
    championshipsService.getAllTimeRanking
      .mockRejectedValueOnce(new Error('Ranking unavailable'))
      .mockResolvedValueOnce([
        { position: 1, public_display_name: 'Ranking recuperado', weighted_points: 25 },
      ]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'No se ha podido cargar el ranking histórico.',
    );

    await user.click(screen.getByRole('button', { name: 'Reintentar ranking' }));

    expect(await screen.findByText('Ranking recuperado')).toBeInTheDocument();
    expect(championshipsService.getAllTimeRanking).toHaveBeenCalledTimes(2);
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(1);
  });
});
