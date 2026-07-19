import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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
    expect(screen.getByRole('link', { name: '← Volver al calendario de la categoría' }))
      .toHaveAttribute('href', '/categories/4/schedule');
  });

  it('allows a failed direct access to retry or return to Torneos', async () => {
    const user = userEvent.setup();
    matchesService.getMatch
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce({
        id: 18,
        status: 'scheduled',
        home_entry: { player: { nickname: 'Local RC' } },
        away_entry: { player: { nickname: 'Visitante RC' } },
      });

    renderWithProviders(<MatchDetails />, {
      route: '/matches/18',
      routePath: '/matches/:matchId',
      authValue: { token: null },
    });

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar el partido.');
    expect(screen.getByRole('link', { name: '← Volver a Torneos' }))
      .toHaveAttribute('href', '/torneos');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('heading', { name: 'Partido: Local RC contra Visitante RC' }))
      .toBeInTheDocument();
  });
});
