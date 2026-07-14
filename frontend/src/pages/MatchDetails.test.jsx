import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { matchesService } from '../api/matches';
import { renderWithProviders } from '../test/renderWithProviders';
import MatchDetails from './MatchDetails';

vi.mock('../api/matches', () => ({
  matchesService: {
    getMatch: vi.fn(),
  },
}));

describe('MatchDetails', () => {
  beforeEach(() => {
    matchesService.getMatch.mockReset();
  });

  it('exposes exactly one coherent main heading', async () => {
    matchesService.getMatch.mockResolvedValue({
      id: 18,
      status: 'scheduled',
      scheduled_date: '2026-07-14T18:00:00Z',
      home_score: null,
      away_score: null,
      home_entry: { player: { nickname: 'Local RC' } },
      away_entry: { player: { nickname: 'Visitante RC' } },
      venue: { name: 'Pista RC' },
      round: {
        name: 'Jornada RC',
        category: {
          id: 4,
          name: 'Categoría RC',
          championship: { name: 'Campeonato RC' },
        },
      },
    });

    renderWithProviders(<MatchDetails />, {
      route: '/matches/18',
      routePath: '/matches/:matchId',
      authValue: { token: null },
    });

    expect(await screen.findByRole('heading', {
      name: 'Partido: Local RC contra Visitante RC',
      level: 1,
    })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });
});
