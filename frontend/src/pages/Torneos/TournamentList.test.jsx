import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useLocation } from 'react-router-dom';
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

const seasons = [
  { id: 7, name: 'Temporada 2026' },
  { id: 8, name: 'Temporada 2027' },
];

const LocationProbe = () => {
  const location = useLocation();

  return <output data-testid="location">{`${location.pathname}${location.search}`}</output>;
};

const renderList = (route = '/torneos') => renderWithProviders(
  <>
    <TournamentList />
    <LocationProbe />
  </>,
  { route },
);

describe('TournamentList', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getChampionships.mockReset();
    championshipsService.getSeasons.mockResolvedValue([]);
  });

  it('shows one real action per championship with readable domain labels', async () => {
    championshipsService.getChampionships.mockResolvedValue([championship]);

    renderList();

    expect(screen.getByRole('heading', { name: 'Campeonatos', level: 1 })).toBeInTheDocument();
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

  it('initializes the real season filter from the query and preserves it on render', async () => {
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([championship]);

    renderList('/torneos?season_id=7');

    expect(await screen.findByLabelText('Temporada')).toHaveValue('7');
    expect(screen.getByTestId('location')).toHaveTextContent('/torneos?season_id=7');
    await waitFor(() => {
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '7',
        type: '',
        status: '',
      });
    });
  });

  it('synchronizes a season selector change while preserving unrelated query parameters', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([]);

    renderList('/torneos?season_id=7&source=competition');

    await user.selectOptions(await screen.findByLabelText('Temporada'), '8');

    await waitFor(() => {
      expect(screen.getByTestId('location'))
        .toHaveTextContent('/torneos?season_id=8&source=competition');
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '8',
        type: '',
        status: '',
      });
    });
  });

  it('removes only season_id when the season filter is cleared', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([]);

    renderList('/torneos?source=competition&season_id=7');

    await user.selectOptions(await screen.findByLabelText('Temporada'), '');

    await waitFor(() => {
      expect(screen.getByTestId('location')).toHaveTextContent('/torneos?source=competition');
      expect(screen.getByLabelText('Temporada')).toHaveValue('');
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '',
        type: '',
        status: '',
      });
    });
  });

  it.each([
    ['/torneos?season_id=abc', '/torneos'],
    ['/torneos?season_id=&source=competition', '/torneos?source=competition'],
    ['/torneos?season_id=0', '/torneos'],
  ])('normalizes an invalid or empty season query without breaking (%s)', async (route, normalizedRoute) => {
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([]);

    renderList(route);

    expect(await screen.findByLabelText('Temporada')).toHaveValue('');
    await waitFor(() => {
      expect(screen.getByTestId('location')).toHaveTextContent(normalizedRoute);
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '',
        type: '',
        status: '',
      });
    });
  });

  it('clears a numeric season query that does not exist after seasons load', async () => {
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([]);

    renderList('/torneos?season_id=999');

    await waitFor(() => {
      expect(screen.getByTestId('location')).toHaveTextContent('/torneos');
      expect(screen.getByLabelText('Temporada')).toHaveValue('');
    });
    await waitFor(() => {
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '999',
        type: '',
        status: '',
      });
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '',
        type: '',
        status: '',
      });
    });
  });

  it('keeps the season query stable when another filter changes', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons.mockResolvedValue(seasons);
    championshipsService.getChampionships.mockResolvedValue([]);

    renderList('/torneos?season_id=7');

    await user.selectOptions(await screen.findByLabelText('Modalidad'), 'singles');

    await waitFor(() => {
      expect(screen.getByTestId('location')).toHaveTextContent('/torneos?season_id=7');
      expect(championshipsService.getChampionships).toHaveBeenCalledWith({
        season_id: '7',
        type: 'singles',
        status: '',
      });
    });
  });

  it('distinguishes filtered empty results from an API error', async () => {
    championshipsService.getChampionships.mockResolvedValue([]);
    const { unmount } = renderList();

    expect(await screen.findByText('No hay campeonatos para los filtros seleccionados.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    unmount();

    championshipsService.getChampionships.mockRejectedValue(new Error('Unavailable'));
    renderList();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se han podido cargar los campeonatos.',
    );
  });

  it('retries the championship list after an error', async () => {
    const user = userEvent.setup();
    championshipsService.getChampionships
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([championship]);

    renderList();

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByRole('heading', { name: 'Campeonato E2E' })).toBeInTheDocument();
    expect(championshipsService.getChampionships).toHaveBeenCalledTimes(2);
  });
});
