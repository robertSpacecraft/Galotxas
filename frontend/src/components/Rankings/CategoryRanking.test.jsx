import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CategoryRanking } from './CategoryRanking';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getChampionship: vi.fn(),
    getCategoryStandings: vi.fn(),
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

const categoryDetails = {
  10: { categories: [{ id: 100, name: 'Categoría Uno' }] },
  11: { categories: [{ id: 110, name: 'Categoría Dos' }] },
  20: { categories: [{ id: 200, name: 'Categoría Tres' }] },
};

const rankingRow = (name, position = 1) => ({
  position,
  public_display_name: name,
  played: 3,
  wins: 2,
  losses: 1,
  games_for: 30,
  games_against: 21,
  games_diff: 9,
  points: 6,
});

const deferred = () => {
  let resolve;
  const promise = new Promise((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
};

const renderRanking = (props = {}) => renderWithProviders(
  <CategoryRanking
    seasons={seasons}
    seasonStatus="content"
    onRetrySeasons={vi.fn()}
    {...props}
  />,
);

describe('CategoryRanking', () => {
  beforeEach(() => {
    championshipsService.getChampionship.mockReset();
    championshipsService.getCategoryStandings.mockReset();
    championshipsService.getChampionship.mockImplementation(async (id) => categoryDetails[id]);
    championshipsService.getCategoryStandings.mockImplementation(async (id) => (
      [rankingRow(`Clasificación ${id}`)]
    ));
  });

  it('follows season, championship and category order without calculating positions', async () => {
    const user = userEvent.setup();
    renderRanking();

    const seasonSelect = screen.getByRole('combobox', { name: 'Temporada de la categoría' });
    const championshipSelect = screen.getByRole('combobox', {
      name: 'Campeonato de la categoría',
    });
    const categorySelect = screen.getByRole('combobox', { name: 'Categoría' });

    expect(seasonSelect).toHaveValue('1');
    expect(championshipSelect).toHaveValue('10');
    expect(await screen.findByText('Clasificación 100')).toBeInTheDocument();
    expect(categorySelect).toHaveValue('100');

    await user.selectOptions(seasonSelect, '2');

    expect(championshipSelect).toHaveValue('20');
    expect(await screen.findByText('Clasificación 200')).toBeInTheDocument();
    expect(categorySelect).toHaveValue('200');
    expect(championshipsService.getChampionship).toHaveBeenLastCalledWith('20');
    expect(championshipsService.getCategoryStandings).toHaveBeenLastCalledWith('200');
    expect(championshipsService.getChampionship).toHaveBeenCalledTimes(2);
    expect(championshipsService.getCategoryStandings).toHaveBeenCalledTimes(2);
    expect(within(screen.getByRole('row', { name: /Clasificación 200/ })).getAllByRole('cell')[0])
      .toHaveTextContent('1');
  });

  it('distinguishes absent championships and categories', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionship.mockImplementation(async (id) => (
      id === '11' ? { categories: [] } : categoryDetails[id]
    ));

    renderRanking();
    await screen.findByText('Clasificación 100');

    await user.selectOptions(
      screen.getByRole('combobox', { name: 'Campeonato de la categoría' }),
      '11',
    );
    expect(await screen.findByText('Este campeonato no tiene categorías públicas disponibles.'))
      .toBeInTheDocument();

    await user.selectOptions(
      screen.getByRole('combobox', { name: 'Temporada de la categoría' }),
      '3',
    );
    expect(screen.getByText('Esta temporada no tiene campeonatos públicos disponibles.'))
      .toBeInTheDocument();
  });

  it('retries failures from the championship detail and category standings independently', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionship
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce(categoryDetails[10]);
    championshipsService.getCategoryStandings
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([rankingRow('Clasificación recuperada')]);

    renderRanking();

    await user.click(await screen.findByRole('button', { name: 'Reintentar categorías' }));
    await user.click(await screen.findByRole('button', { name: 'Reintentar clasificación' }));

    expect(await screen.findByText('Clasificación recuperada')).toBeInTheDocument();
    expect(championshipsService.getChampionship).toHaveBeenCalledTimes(2);
    expect(championshipsService.getCategoryStandings).toHaveBeenCalledTimes(2);
  });

  it('ignores stale parent details and standings after changing selections', async () => {
    const user = userEvent.setup();
    const staleDetail = deferred();
    const staleStandings = deferred();
    const currentStandings = deferred();
    championshipsService.getChampionship.mockImplementation((id) => {
      if (id === '10') return staleDetail.promise;

      return Promise.resolve({
        categories: [
          { id: 110, name: 'Categoría vigente A' },
          { id: 111, name: 'Categoría vigente B' },
        ],
      });
    });
    championshipsService.getCategoryStandings.mockImplementation((id) => (
      id === '110' ? staleStandings.promise : currentStandings.promise
    ));

    renderRanking();
    await waitFor(() => expect(championshipsService.getChampionship).toHaveBeenCalledWith('10'));

    await user.selectOptions(
      screen.getByRole('combobox', { name: 'Campeonato de la categoría' }),
      '11',
    );
    const categorySelect = await screen.findByRole('combobox', { name: 'Categoría' });
    expect(categorySelect).toHaveValue('110');

    await user.selectOptions(categorySelect, '111');
    await act(async () => {
      currentStandings.resolve([rankingRow('Respuesta vigente', 2)]);
      await currentStandings.promise;
    });
    expect(screen.getByText('Respuesta vigente')).toBeInTheDocument();

    await act(async () => {
      staleDetail.resolve({ categories: [{ id: 100, name: 'Categoría obsoleta' }] });
      staleStandings.resolve([rankingRow('Respuesta obsoleta', 8)]);
      await Promise.all([staleDetail.promise, staleStandings.promise]);
    });

    expect(categorySelect).toHaveValue('111');
    expect(screen.queryByText('Categoría obsoleta')).not.toBeInTheDocument();
    expect(screen.queryByText('Respuesta obsoleta')).not.toBeInTheDocument();
  });
});
