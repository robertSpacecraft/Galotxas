import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../api/championships';
import { renderWithProviders } from '../test/renderWithProviders';
import Standings from './Standings';

vi.mock('../api/championships', () => ({
  championshipsService: {
    getCategory: vi.fn(),
    getCategoryStandings: vi.fn(),
    getCategoryOfficialResults: vi.fn(),
  },
}));

const category = {
  id: 12,
  name: 'Individual E2E',
  championship: {
    name: 'Campeonato E2E',
    season: { name: 'Temporada E2E' },
  },
};

const renderStandings = () => renderWithProviders(<Standings />, {
  route: '/categories/12/standings',
  routePath: '/categories/:categoryId/standings',
});

describe('Standings', () => {
  beforeEach(() => {
    championshipsService.getCategory.mockReset();
    championshipsService.getCategoryStandings.mockReset();
    championshipsService.getCategoryOfficialResults.mockReset();
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: null,
      cup: null,
    });
  });

  it('renders hierarchy, contextual navigation and backend positions without recalculating them', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([
      {
        position: 7,
        public_display_name: 'Pilotari E2E',
        played: 3,
        wins: 2,
        losses: 1,
        games_for: 28,
        games_against: 21,
        games_diff: 7,
        points: 6,
      },
    ]);

    renderStandings();

    expect(await screen.findByRole('heading', { name: 'Clasificación de Individual E2E' }))
      .toBeInTheDocument();
    expect(screen.getByText('Temporada E2E · Campeonato E2E')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '← Volver a la categoría' }))
      .toHaveAttribute('href', '/categories/12');
    expect(screen.getByRole('link', { name: 'Clasificación' }))
      .toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('heading', { name: 'Clasificación actual', level: 2 }))
      .toBeInTheDocument();

    const row = screen.getByRole('row', { name: /Pilotari E2E/ });
    expect(within(row).getAllByRole('cell')[0]).toHaveTextContent('7');
    expect(row).toHaveTextContent('3');
    expect(screen.getByRole('region', { name: 'Tabla de clasificación de Individual E2E' }))
      .toHaveAttribute('tabindex', '0');
  });

  it('distinguishes an empty classification from a load failure', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([]);

    const { unmount } = renderStandings();

    expect(await screen.findByText('Todavía no hay participantes o resultados en esta clasificación.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    unmount();

    championshipsService.getCategoryStandings.mockRejectedValue(new Error('Unavailable'));
    renderStandings();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se ha podido cargar la clasificación.',
    );
  });

  it('retries a failed classification request', async () => {
    const user = userEvent.setup();
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([]);

    renderStandings();

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByText('Todavía no hay participantes o resultados en esta clasificación.'))
      .toBeInTheDocument();
    expect(championshipsService.getCategoryStandings).toHaveBeenCalledTimes(2);
  });

  it('uses only the persisted official ranking when it differs from live standings', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([{
      position: 1,
      public_display_name: 'Líder vivo',
      played: 4,
      points: 8,
    }]);
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: {
        version: 2,
        officialized_at: '2026-09-06T10:20:30.000000Z',
        ranking: [{
          position: 1,
          public_display_name: 'Campeona congelada',
          played: 9,
          wins: 8,
          losses: 1,
          points: 16,
          games_for: 90,
          games_against: 54,
          games_diff: 36,
        }],
      },
      cup: null,
    });

    renderStandings();

    expect(await screen.findByRole('heading', { name: 'Clasificación oficial', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByRole('row', { name: /Campeona congelada/ })).toHaveTextContent('16');
    expect(screen.queryByText('Líder vivo')).not.toBeInTheDocument();
    expect(screen.queryByText(/versión 2/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/2026-09-06/)).not.toBeInTheDocument();
  });

  it('uses live standings as the current classification when League is null', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([{
      position: 4,
      public_display_name: 'Líder actual',
      points: 6,
    }]);

    renderStandings();

    expect(await screen.findByRole('heading', { name: 'Clasificación actual', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByRole('row', { name: /Líder actual/ })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Clasificación oficial' }))
      .not.toBeInTheDocument();
  });

  it('keeps live standings current and warns when official-results fails', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([{
      position: 2,
      public_display_name: 'Clasificación disponible',
      points: 7,
    }]);
    championshipsService.getCategoryOfficialResults.mockRejectedValue(new Error('Unavailable'));

    renderStandings();

    expect(await screen.findByRole('row', { name: /Clasificación disponible/ }))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Clasificación actual', level: 2 }))
      .toBeInTheDocument();
    expect(screen.getByText(
      'No se ha podido comprobar el resultado oficial. Se muestra la clasificación actual.',
    )).toHaveAttribute('role', 'status');
    expect(screen.queryByRole('heading', { name: 'Clasificación oficial' }))
      .not.toBeInTheDocument();
  });
});
