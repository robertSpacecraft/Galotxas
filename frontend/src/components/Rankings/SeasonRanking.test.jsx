import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { SeasonRanking } from './SeasonRanking';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getSeasonRanking: vi.fn(),
  },
}));

const props = {
  seasons: [{ id: 3, name: 'Temporada pública' }],
  selectedSeasonId: 3,
  onSeasonChange: vi.fn(),
  seasonStatus: 'content',
  onRetrySeasons: vi.fn(),
};

describe('SeasonRanking', () => {
  beforeEach(() => {
    championshipsService.getSeasonRanking.mockReset();
  });

  it('renders the positions and order received from the backend', async () => {
    championshipsService.getSeasonRanking.mockResolvedValue([
      {
        position: 7,
        public_display_name: 'Pilotari backend',
        played: 2,
        wins: 1,
        losses: 1,
        weighted_points: 4,
        games_for: 20,
        games_against: 18,
        games_diff: 2,
        categories_played_count: 1,
        categories_played_list: ['Individual'],
      },
    ]);

    renderWithProviders(<SeasonRanking {...props} />);

    const row = await screen.findByRole('row', { name: /Pilotari backend/ });
    expect(row).toHaveTextContent('7');
    expect(championshipsService.getSeasonRanking).toHaveBeenCalledWith(3);
  });

  it('distinguishes errors from empty data and retries the same season', async () => {
    const user = userEvent.setup();
    championshipsService.getSeasonRanking
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce([]);

    renderWithProviders(<SeasonRanking {...props} />);
    await user.click(await screen.findByRole('button', { name: 'Reintentar ranking' }));

    expect(await screen.findByText('Todavía no hay datos de ranking para esta temporada.'))
      .toBeInTheDocument();
    expect(championshipsService.getSeasonRanking).toHaveBeenCalledTimes(2);
  });
});
