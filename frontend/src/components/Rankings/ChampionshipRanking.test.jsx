import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { ChampionshipRanking } from './ChampionshipRanking';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getChampionshipRanking: vi.fn(),
  },
}));

const seasons = [
  {
    id: 1,
    name: 'Temporada Uno',
    championships: [
      { id: 10, name: 'Campeonato Uno' },
      { id: 11, name: 'Campeonato Dos' },
    ],
  },
  {
    id: 2,
    name: 'Temporada Dos',
    championships: [{ id: 20, name: 'Campeonato Tres' }],
  },
  { id: 3, name: 'Temporada Vacía', championships: [] },
];

const rankingRow = (name, position = 1) => ({
  position,
  public_display_name: name,
  played: 3,
  wins: 2,
  losses: 1,
  raw_points: 6,
  weighted_points: 7,
  games_for: 30,
  games_against: 21,
  games_diff: 9,
  categories_played_list: ['Individual'],
});

const deferred = () => {
  let resolve;
  const promise = new Promise((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
};

const renderRanking = (props = {}) => renderWithProviders(
  <ChampionshipRanking
    seasons={seasons}
    seasonStatus="content"
    onRetrySeasons={vi.fn()}
    {...props}
  />,
);

describe('ChampionshipRanking', () => {
  beforeEach(() => {
    championshipsService.getChampionshipRanking.mockReset();
    championshipsService.getChampionshipRanking.mockResolvedValue([]);
  });

  it('selects the first API options and resets the championship with its season', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionshipRanking.mockImplementation(async (id) => (
      [rankingRow(`Ranking ${id}`)]
    ));

    renderRanking();

    const seasonSelect = screen.getByRole('combobox', { name: 'Temporada del campeonato' });
    const championshipSelect = screen.getByRole('combobox', { name: 'Campeonato' });
    expect(seasonSelect).toHaveValue('1');
    expect(championshipSelect).toHaveValue('10');
    expect(await screen.findByText('Ranking 10')).toBeInTheDocument();

    await user.selectOptions(seasonSelect, '2');

    expect(championshipSelect).toHaveValue('20');
    expect(await screen.findByText('Ranking 20')).toBeInTheDocument();
    expect(championshipsService.getChampionshipRanking).toHaveBeenLastCalledWith('20');
  });

  it('distinguishes a season without championships and retries a ranking failure', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionshipRanking
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([rankingRow('Respuesta recuperada')]);

    renderRanking();

    await user.click(await screen.findByRole('button', { name: 'Reintentar ranking' }));
    expect(await screen.findByText('Respuesta recuperada')).toBeInTheDocument();

    await user.selectOptions(
      screen.getByRole('combobox', { name: 'Temporada del campeonato' }),
      '3',
    );
    expect(screen.getByText('Esta temporada no tiene campeonatos públicos disponibles.'))
      .toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Campeonato' })).toBeDisabled();
  });

  it('ignores a slower ranking response after changing championship', async () => {
    const user = userEvent.setup();
    const firstRequest = deferred();
    const secondRequest = deferred();
    championshipsService.getChampionshipRanking.mockImplementation((id) => (
      id === '10' ? firstRequest.promise : secondRequest.promise
    ));

    renderRanking();
    await waitFor(() => (
      expect(championshipsService.getChampionshipRanking).toHaveBeenCalledWith('10')
    ));

    await user.selectOptions(screen.getByRole('combobox', { name: 'Campeonato' }), '11');
    expect(championshipsService.getChampionshipRanking).toHaveBeenCalledWith('11');

    await act(async () => {
      secondRequest.resolve([rankingRow('Respuesta vigente', 2)]);
      await secondRequest.promise;
    });
    expect(screen.getByText('Respuesta vigente')).toBeInTheDocument();

    await act(async () => {
      firstRequest.resolve([rankingRow('Respuesta obsoleta', 9)]);
      await firstRequest.promise;
    });
    expect(screen.queryByText('Respuesta obsoleta')).not.toBeInTheDocument();
    expect(within(screen.getByRole('row', { name: /Respuesta vigente/ })).getAllByRole('cell')[0])
      .toHaveTextContent('2');
  });
});
