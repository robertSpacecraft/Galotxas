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
  });

  it('renders hierarchy, contextual navigation and backend positions without recalculating them', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategoryStandings.mockResolvedValue([
      {
        position: 7,
        entry_id: 4,
        name: 'Pilotari E2E',
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
});
