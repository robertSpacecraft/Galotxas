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
    expect(screen.getByText('Desde 14/7/2026')).toBeInTheDocument();
    expect(screen.getByText('Sin fechas definidas')).toBeInTheDocument();
    expect(screen.getAllByText(/Sin fecha/)).toHaveLength(1);
    expect(screen.queryByText(/1970/)).not.toBeInTheDocument();
  });

  it('offers the existing detail, standings and schedule routes for each category', async () => {
    championshipsService.getChampionship.mockResolvedValue({
      id: 9,
      name: 'Torneo RC',
      description: 'Torneo de endurecimiento.',
      type: 'singles',
      status: 'active',
      start_date: '2026-07-14',
      end_date: '2026-07-20',
      registration_status: 'closed',
      registration_starts_at: null,
      registration_ends_at: null,
      registration_is_open: false,
      season: { name: 'Temporada 2026' },
      categories: [{
        id: 12,
        name: 'Individual absoluta',
        status: 'active',
        gender: 'mixed',
        level: 5,
      }],
    });
    championshipsService.getChampionshipRanking.mockResolvedValue([]);

    renderWithProviders(<TournamentDetail />, {
      route: '/torneos/9',
      routePath: '/torneos/:championshipId',
      authValue: anonymousAuth,
    });

    const actions = await screen.findByRole('navigation', { name: 'Opciones de Individual absoluta' });
    expect(actions).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver categoría' }))
      .toHaveAttribute('href', '/categories/12');
    expect(screen.getByRole('link', { name: 'Clasificación' }))
      .toHaveAttribute('href', '/categories/12/standings');
    expect(screen.getByRole('link', { name: 'Calendario y resultados' }))
      .toHaveAttribute('href', '/categories/12/schedule');
    expect(screen.getByText('Activa')).toBeInTheDocument();
    expect(screen.getByText('Mixta')).toBeInTheDocument();
    expect(screen.getByText('Nivel 5')).toBeInTheDocument();
  });

  it('keeps the championship usable when its independent ranking fails', async () => {
    championshipsService.getChampionship.mockResolvedValue({
      id: 9,
      name: 'Torneo RC',
      description: null,
      type: 'singles',
      status: 'active',
      registration_status: 'closed',
      registration_is_open: false,
      season: { name: 'Temporada 2026' },
      categories: [],
    });
    championshipsService.getChampionshipRanking.mockRejectedValue(new Error('Unavailable'));

    renderWithProviders(<TournamentDetail />, {
      route: '/torneos/9',
      routePath: '/torneos/:championshipId',
      authValue: anonymousAuth,
    });

    expect(await screen.findByRole('heading', { name: 'Torneo RC', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'No se ha podido cargar el ranking del campeonato.',
    );
    expect(screen.getByRole('heading', { name: 'Categorías' })).toBeInTheDocument();
  });
});
