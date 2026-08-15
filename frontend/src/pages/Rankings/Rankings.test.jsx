import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Rankings } from './Rankings';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
    getAllTimeRanking: vi.fn(),
    getSeasonRanking: vi.fn(),
    getChampionship: vi.fn(),
    getChampionshipRanking: vi.fn(),
    getCategoryStandings: vi.fn(),
  },
}));

describe('Rankings', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getAllTimeRanking.mockReset();
    championshipsService.getSeasonRanking.mockReset();
    championshipsService.getChampionship.mockReset();
    championshipsService.getChampionshipRanking.mockReset();
    championshipsService.getCategoryStandings.mockReset();
    championshipsService.getSeasons.mockResolvedValue([
      {
        id: 2,
        name: 'Primera en respuesta',
        championships: [{ id: 20, name: 'Campeonato primero' }],
      },
      {
        id: 99,
        name: 'Segunda en respuesta',
        championships: [{ id: 90, name: 'Campeonato segundo' }],
      },
    ]);
    championshipsService.getAllTimeRanking.mockResolvedValue([]);
    championshipsService.getSeasonRanking.mockResolvedValue([]);
    championshipsService.getChampionship.mockResolvedValue({ categories: [] });
    championshipsService.getChampionshipRanking.mockResolvedValue([]);
    championshipsService.getCategoryStandings.mockResolvedValue([]);
  });

  it('provides four accessible tabs, defaults to historical and exposes only one panel', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Rankings />);

    expect(screen.getByRole('link', { name: '← Volver a Competición' }))
      .toHaveAttribute('href', '/competicion');
    const historicalTab = screen.getByRole('tab', { name: 'Histórico' });
    const seasonTab = screen.getByRole('tab', { name: 'Temporada' });
    const championshipTab = screen.getByRole('tab', { name: 'Campeonato' });
    const categoryTab = screen.getByRole('tab', { name: 'Categoría' });
    expect(screen.getAllByRole('tab')).toHaveLength(4);
    expect(historicalTab).toHaveAttribute('aria-selected', 'true');
    expect(historicalTab).toHaveAttribute('aria-controls', 'all-time-ranking-panel');
    expect(seasonTab).toHaveAttribute('aria-controls', 'season-ranking-panel');
    expect(championshipTab).toHaveAttribute('aria-controls', 'championship-ranking-panel');
    expect(categoryTab).toHaveAttribute('aria-controls', 'category-ranking-panel');
    expect(screen.getAllByRole('tabpanel')).toHaveLength(1);

    seasonTab.focus();
    await user.keyboard('{Enter}');

    expect(seasonTab).toHaveAttribute('aria-selected', 'true');
    const select = await screen.findByRole('combobox', { name: 'Temporada' });
    expect(select).toHaveValue('2');
    expect([...select.options].map((option) => option.textContent)).toEqual([
      'Primera en respuesta',
      'Segunda en respuesta',
    ]);
    expect(championshipsService.getSeasonRanking).toHaveBeenCalledWith(2);
    expect(screen.getAllByRole('tabpanel')).toHaveLength(1);
  });

  it('announces the current four-scope metadata', async () => {
    renderWithProviders(<Rankings />);

    expect(await screen.findByRole('heading', { name: 'Rankings de Galotxas' }))
      .toBeInTheDocument();
    expect(document.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta los rankings públicos de Galotxas por histórico, temporada, campeonato y categoría.',
    );
  });

  it('allows retrying the shared season hierarchy from a scoped panel', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasons
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([
        { id: 7, name: 'Temporada recuperada', championships: [] },
      ]);

    renderWithProviders(<Rankings />);
    await user.click(screen.getByRole('tab', { name: 'Campeonato' }));
    await user.click(await screen.findByRole('button', { name: 'Reintentar temporadas' }));

    expect(await screen.findByRole('combobox', { name: 'Temporada del campeonato' }))
      .toHaveValue('7');
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(2);
  });
});
