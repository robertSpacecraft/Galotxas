import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { TournamentDetail } from './TournamentDetail';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getChampionship: vi.fn(),
    getChampionshipRanking: vi.fn(),
    getRegistrationStatus: vi.fn(),
    registerChampionship: vi.fn(),
  },
}));

const anonymousAuth = {
  user: null,
  isAuthenticated: false,
};

describe('TournamentDetail', () => {
  beforeEach(() => {
    championshipsService.getChampionship.mockReset();
    championshipsService.getChampionshipRanking.mockReset();
  });

  it('renders valid dates and safe fallbacks without Unix epoch dates', async () => {
    championshipsService.getChampionship.mockResolvedValue({
      id: 9,
      name: 'Torneo RC',
      description: 'Torneo de endurecimiento.',
      type: 'singles',
      status: 'active',
      start_date: '2026-07-14',
      end_date: null,
      registration_status: 'closed',
      registration_starts_at: undefined,
      registration_ends_at: 'invalid-date',
      registration_is_open: false,
      season: { name: 'Temporada 2026' },
      categories: [],
    });
    championshipsService.getChampionshipRanking.mockResolvedValue([]);

    renderWithProviders(<TournamentDetail />, {
      route: '/torneos/9',
      routePath: '/torneos/:championshipId',
      authValue: anonymousAuth,
    });

    expect(await screen.findByRole('heading', { name: 'Torneo RC', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('14/7/2026 - Sin fecha definida')).toBeInTheDocument();
    expect(screen.getByText('Abierta desde: Sin fecha definida')).toBeInTheDocument();
    expect(screen.getByText('Hasta: Sin fecha definida')).toBeInTheDocument();
    expect(screen.queryByText(/1970/)).not.toBeInTheDocument();
  });
});
