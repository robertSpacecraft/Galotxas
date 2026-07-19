import { screen } from '@testing-library/react';
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
      championship: { name: 'Torneo RC' },
    });
    championshipsService.getCategoryStandings.mockResolvedValue([]);
    championshipsService.getCategorySchedule.mockResolvedValue([]);

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    expect(await screen.findByRole('heading', { name: 'Individual absoluta', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByRole('link', { name: '← Volver al torneo' }))
      .toHaveAttribute('href', '/torneos/9');
    expect(screen.getByRole('link', { name: 'Ver clasificación completa' }))
      .toHaveAttribute('href', '/categories/12/standings');
    expect(screen.getByRole('link', { name: 'Ver calendario y resultados' }))
      .toHaveAttribute('href', '/categories/12/schedule');
  });
});
