import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { TournamentList } from './TournamentList';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getChampionships: vi.fn(),
  },
}));

const championship = {
  id: 9,
  name: 'Campeonato E2E',
  type: 'singles',
  status: 'active',
  start_date: '2026-07-01',
  end_date: null,
  registration_is_open: true,
  season: { name: 'Temporada E2E' },
};

describe('TournamentList', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getChampionships.mockReset();
    championshipsService.getSeasons.mockResolvedValue([]);
  });

  it('shows one real action per championship with readable domain labels', async () => {
    championshipsService.getChampionships.mockResolvedValue([championship]);

    renderWithProviders(<TournamentList />);

    const article = await screen.findByRole('article');
    expect(within(article).getByRole('heading', { name: 'Campeonato E2E', level: 2 }))
      .toBeInTheDocument();
    expect(within(article).getByText('Individual')).toBeInTheDocument();
    expect(within(article).getByText('Activo')).toBeInTheDocument();
    expect(within(article).getByText('Desde 1/7/2026')).toBeInTheDocument();
    expect(within(article).getAllByRole('link')).toHaveLength(1);
    expect(within(article).getByRole('link', { name: 'Ver campeonato' }))
      .toHaveAttribute('href', '/torneos/9');
    expect(screen.getByRole('link', { name: '← Volver a Competición' }))
      .toHaveAttribute('href', '/competicion');
  });

  it('distinguishes filtered empty results from an API error', async () => {
    championshipsService.getChampionships.mockResolvedValue([]);
    const { unmount } = renderWithProviders(<TournamentList />);

    expect(await screen.findByText('No hay campeonatos para los filtros seleccionados.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    unmount();

    championshipsService.getChampionships.mockRejectedValue(new Error('Unavailable'));
    renderWithProviders(<TournamentList />);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se han podido cargar los campeonatos.',
    );
  });

  it('retries the championship list after an error', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionships
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([championship]);

    renderWithProviders(<TournamentList />);

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByRole('heading', { name: 'Campeonato E2E' })).toBeInTheDocument();
    expect(championshipsService.getChampionships).toHaveBeenCalledTimes(2);
  });
});
