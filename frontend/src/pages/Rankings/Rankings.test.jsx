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
  },
}));

describe('Rankings', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
    championshipsService.getAllTimeRanking.mockReset();
    championshipsService.getSeasonRanking.mockReset();
    championshipsService.getSeasons.mockResolvedValue([
      { id: 2, name: 'Primera en respuesta' },
      { id: 99, name: 'Segunda en respuesta' },
    ]);
    championshipsService.getAllTimeRanking.mockResolvedValue([]);
    championshipsService.getSeasonRanking.mockResolvedValue([]);
  });

  it('provides an accessible tab flow and keeps the season order from the API', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Rankings />);

    expect(screen.getByRole('link', { name: '← Volver a Competición' }))
      .toHaveAttribute('href', '/competicion');
    const historicalTab = screen.getByRole('tab', { name: 'Ranking histórico' });
    const seasonTab = screen.getByRole('tab', { name: 'Ranking de temporada' });
    expect(historicalTab).toHaveAttribute('aria-selected', 'true');

    await user.click(seasonTab);

    expect(seasonTab).toHaveAttribute('aria-selected', 'true');
    const select = await screen.findByRole('combobox', { name: 'Temporada' });
    expect(select).toHaveValue('2');
    expect([...select.options].map((option) => option.textContent)).toEqual([
      'Primera en respuesta',
      'Segunda en respuesta',
    ]);
    expect(championshipsService.getSeasonRanking).toHaveBeenCalledWith(2);
  });
});
