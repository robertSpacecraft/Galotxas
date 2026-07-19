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
  },
}));

const publicSeasons = [
  {
    id: 7,
    name: 'Temporada 2026',
    slug: null,
    status: 'active',
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    is_public: true,
    created_at: '2026-01-01T00:00:00Z',
    championships: [
      {
        id: 22,
        name: 'Trofeu de Galotxas',
        slug: 'trofeu-de-galotxas',
        type: 'singles',
        status: 'active',
        categories_count: 2,
        is_public: true,
        description: null,
      },
    ],
  },
];

const renderPage = () => renderWithProviders(<CompetitionPage />, { route: '/competicion' });

const expectGlobalDestinations = () => {
  expect(screen.getByRole('link', { name: /Torneos/ })).toHaveAttribute('href', '/torneos');
  expect(screen.getByRole('link', { name: /Rankings/ })).toHaveAttribute('href', '/rankings');
};

describe('CompetitionPage', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getChampionships.mockReset();
  });

  it('shows the public season hierarchy and keeps the common landing contract', async () => {
    championshipsService.getSeasons.mockResolvedValue(publicSeasons);

    const { container } = renderPage();

    expect(screen.getByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toHaveAttribute('id', 'competition-season-7-title');
    expect(screen.getByRole('region', { name: 'Temporada 2026' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Trofeu de Galotxas', level: 4 }))
      .toHaveAttribute('id', 'competition-championship-22-title');
    expect(screen.getByRole('article', { name: 'Trofeu de Galotxas' })).toBeInTheDocument();
    expect(screen.getByText('Activa')).toBeInTheDocument();
    expect(screen.getByText('Activo')).toBeInTheDocument();
    expect(screen.getByText('Individual')).toBeInTheDocument();
    expect(screen.getByText('2 categorías')).toBeInTheDocument();
    expect(screen.getByText('1/1/2026')).toBeInTheDocument();
    expect(screen.getByText('31/12/2026')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver detalle de Trofeu de Galotxas' }))
      .toHaveAttribute('href', '/torneos/22');
    expect(container).not.toHaveTextContent('is_public');
    expect(container).not.toHaveTextContent('trofeu-de-galotxas');
    expect(container).not.toHaveTextContent('2026-01-01T00:00:00Z');
    expectGlobalDestinations();
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(1);
    expect(championshipsService.getChampionships).not.toHaveBeenCalled();
    expect(document.title).toBe('Competición | Galotxas');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta temporadas y campeonatos públicos, calendarios, resultados y clasificaciones de Galotxas.',
    );
  });

  it('announces loading without showing loaded season content', () => {
    championshipsService.getSeasons.mockReturnValue(new Promise(() => {}));

    const { container } = renderPage();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando temporadas y campeonatos');
    expect(screen.queryByRole('heading', { level: 3 })).not.toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expectGlobalDestinations();
  });

  it('shows a global empty state without simulated cards', async () => {
    championshipsService.getSeasons.mockResolvedValue([]);

    renderPage();

    expect(await screen.findByText('No hay temporadas disponibles en este momento.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 3 })).not.toBeInTheDocument();
    expect(screen.queryByText('Trofeu de Galotxas')).not.toBeInTheDocument();
    expectGlobalDestinations();
  });

  it('keeps a season visible when it has no championships', async () => {
    championshipsService.getSeasons.mockResolvedValue([{ ...publicSeasons[0], championships: [] }]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporada 2026', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByText('0 campeonatos')).toBeInTheDocument();
    expect(screen.getByText('Esta temporada todavía no tiene campeonatos disponibles.'))
      .toBeInTheDocument();
    expectGlobalDestinations();
  });

  it('omits nullable dates and optional championship data safely', async () => {
    championshipsService.getSeasons.mockResolvedValue([
      {
        ...publicSeasons[0],
        start_date: null,
        end_date: null,
        championships: [
          {
            ...publicSeasons[0].championships[0],
            categories_count: null,
            description: null,
          },
        ],
      },
      {
        id: 8,
        name: 'Temporada sin colección',
        status: 'planned',
        start_date: null,
        end_date: null,
        championships: null,
      },
    ]);

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Temporada sin colección', level: 3 }))
      .toBeInTheDocument();
    expect(screen.queryByText('Sin fecha definida')).not.toBeInTheDocument();
    expect(screen.queryByText('2 categorías')).not.toBeInTheDocument();
    expect(screen.getByText('Planificada')).toBeInTheDocument();
    expect(screen.getByText('Esta temporada todavía no tiene campeonatos disponibles.'))
      .toBeInTheDocument();
  });

  it('announces an error, preserves destinations and retries the real hook load', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons
      .mockRejectedValueOnce(new Error('Backend detail'))
      .mockResolvedValueOnce(publicSeasons);

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
});
