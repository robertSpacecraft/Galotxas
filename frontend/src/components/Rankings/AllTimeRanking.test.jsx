import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { AllTimeRanking } from './AllTimeRanking';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getAllTimeRanking: vi.fn(),
  },
}));

const buildRow = (position) => ({
  position,
  player_id: position,
  name: `Pilotari ${position}`,
  played: 12,
  wins: 8,
  losses: 4,
  win_rate: 66.7,
  played_singles: 12,
  played_doubles: 0,
  weighted_points: 20 - position,
  weighted_points_per_match: 1.5,
  games_diff_per_match: 1.2,
  official_ranking: true,
  matches_needed_for_official_ranking: 0,
});

describe('AllTimeRanking', () => {
  beforeEach(() => {
    championshipsService.getAllTimeRanking.mockReset();
  });

  it('keeps the full ranking page independent from the five-row landing preview', async () => {
    championshipsService.getAllTimeRanking.mockResolvedValue(
      Array.from({ length: 6 }, (_, index) => buildRow(index + 1)),
    );

    renderWithProviders(<AllTimeRanking />);

    expect(await screen.findByText('Pilotari 1')).toBeInTheDocument();
    expect(screen.getByText('Pilotari 6')).toBeInTheDocument();
    expect(screen.getAllByRole('row')).toHaveLength(7);
    expect(championshipsService.getAllTimeRanking).toHaveBeenCalledTimes(1);
  });
});
