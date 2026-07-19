import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CategoryDetail } from './CategoryDetail';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getCategory: vi.fn(),
    getCategoryStandings: vi.fn(),
    getCategorySchedule: vi.fn(),
  },
}));

describe('CategoryDetail', () => {
  beforeEach(() => {
    championshipsService.getCategory.mockReset();
    championshipsService.getCategoryStandings.mockReset();
    championshipsService.getCategorySchedule.mockReset();
  });

  it('links its championship, full standings and schedule through the existing routes', async () => {
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      championship_id: 9,
      name: 'Individual absoluta',
      status: 'active',
      gender: 'female',
      level: 6,
      championship: {
        id: 9,
        name: 'Torneo RC',
        type: 'singles',
        season: { name: 'Temporada 2026' },
      },
    });

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    expect(await screen.findByRole('heading', { name: 'Individual absoluta', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByRole('link', { name: '← Volver al campeonato' }))
      .toHaveAttribute('href', '/torneos/9');
    expect(screen.getByRole('link', { name: 'Clasificación' }))
      .toHaveAttribute('href', '/categories/12/standings');
    expect(screen.getByRole('link', { name: 'Calendario y resultados' }))
      .toHaveAttribute('href', '/categories/12/schedule');
    expect(screen.getByRole('link', { name: 'Resumen' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('Temporada 2026 · Torneo RC')).toBeInTheDocument();
    expect(screen.getByText('Activa')).toBeInTheDocument();
    expect(screen.getByText('Femenina')).toBeInTheDocument();
    expect(screen.getByText('Nivel 6')).toBeInTheDocument();
    expect(championshipsService.getCategoryStandings).not.toHaveBeenCalled();
    expect(championshipsService.getCategorySchedule).not.toHaveBeenCalled();
  });

  it('offers retry and a safe recovery route when direct access fails', async () => {
    const user = userEvent.setup();
    championshipsService.getCategory
      .mockRejectedValueOnce(new Error('Not found'))
      .mockResolvedValueOnce({
        id: 12,
        championship_id: 9,
        name: 'Individual absoluta',
        championship: { id: 9, name: 'Torneo RC' },
      });

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar la categoría.');
    expect(screen.getByRole('link', { name: '← Volver a Torneos' }))
      .toHaveAttribute('href', '/torneos');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('heading', { name: 'Individual absoluta' })).toBeInTheDocument();
  });
});
