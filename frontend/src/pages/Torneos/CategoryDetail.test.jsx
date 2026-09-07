import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CategoryDetail } from './CategoryDetail';

vi.mock('../../api/championships', () => ({
  championshipsService: {
    getCategory: vi.fn(),
    getCategoryOfficialResults: vi.fn(),
    getCategoryStandings: vi.fn(),
    getCategorySchedule: vi.fn(),
  },
}));

describe('CategoryDetail', () => {
  beforeEach(() => {
    championshipsService.getCategory.mockReset();
    championshipsService.getCategoryOfficialResults.mockReset();
    championshipsService.getCategoryStandings.mockReset();
    championshipsService.getCategorySchedule.mockReset();
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: null,
      cup: null,
    });
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
    expect(screen.getByRole('link', { name: 'Copa' }))
      .toHaveAttribute('href', '/categories/12/cup');
    expect(screen.getByRole('link', { name: 'Resumen' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('Temporada 2026 · Torneo RC')).toBeInTheDocument();
    expect(screen.getByText('Activa')).toBeInTheDocument();
    expect(screen.getByText('Femenina')).toBeInTheDocument();
    expect(screen.getByText('Nivel 6')).toBeInTheDocument();
    expect(screen.getByText('Todavía no hay resultados oficiales vigentes para esta categoría.'))
      .toBeInTheDocument();
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
    expect(screen.getByRole('link', { name: '← Volver a Campeonatos' }))
      .toHaveAttribute('href', '/torneos');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('heading', { name: 'Individual absoluta' })).toBeInTheDocument();
  });

  it('shows the first three official League rows in received order and the explicit Cup winner', async () => {
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      championship_id: 9,
      name: 'Individual absoluta',
      championship: { id: 9, name: 'Torneo RC' },
    });
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: {
        version: 7,
        officialized_at: '2026-09-06T10:20:30.000000Z',
        ranking: [
          { position: 2, public_display_name: 'Segunda recibida' },
          { position: 1, public_display_name: 'Primera recibida' },
          { position: 3, public_display_name: 'Tercera recibida' },
          { position: 4, public_display_name: 'Cuarta excluida' },
        ],
      },
      cup: {
        version: 5,
        officialized_at: '2026-09-06T11:21:31.000000Z',
        champion: { public_display_name: 'Campeona de Copa' },
      },
    });

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    const league = await screen.findByRole('article', { name: 'Liga' });
    const cup = screen.getByRole('article', { name: 'Copa' });
    const podium = within(league).getByRole('list', { name: 'Podio oficial de Liga' });
    const podiumRows = within(podium).getAllByRole('listitem');
    expect(podiumRows).toHaveLength(3);
    expect(podiumRows[0]).toHaveTextContent('2.º');
    expect(podiumRows[0]).toHaveTextContent('Segunda recibida');
    expect(podiumRows[1]).toHaveTextContent('1.º');
    expect(podiumRows[1]).toHaveTextContent('Primera recibida');
    expect(podiumRows[2]).toHaveTextContent('3.º');
    expect(podiumRows[2]).toHaveTextContent('Tercera recibida');
    expect(within(league).queryByText('Cuarta excluida')).not.toBeInTheDocument();
    expect(within(league).getByRole('link', { name: 'Ver Clasificación' }))
      .toHaveAttribute('href', '/categories/12/standings');
    expect(within(cup).getByText('Ganador')).toBeInTheDocument();
    expect(cup).toHaveTextContent('Campeona de Copa');
    expect(within(cup).getByRole('link', { name: 'Ver Copa' }))
      .toHaveAttribute('href', '/categories/12/cup');
    expect(screen.queryByText(/versión 7/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/2026-09-06/)).not.toBeInTheDocument();
  });

  it.each([1, 2])('renders only the %i available official League podium rows', async (rowCount) => {
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      name: 'Individual absoluta',
      championship: { id: 9, name: 'Torneo RC' },
    });
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: {
        ranking: [
          { position: 1, public_display_name: 'Primera clasificada' },
          { position: 2, public_display_name: 'Segunda clasificada' },
        ].slice(0, rowCount),
      },
      cup: null,
    });

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    const league = await screen.findByRole('article', { name: 'Liga' });
    expect(within(league).getAllByRole('listitem')).toHaveLength(rowCount);
    expect(screen.getByRole('article', { name: 'Copa' }))
      .toHaveTextContent('Sin resultado oficial vigente');
  });

  it('keeps the missing part neutral when only Cup has an official result', async () => {
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      name: 'Individual absoluta',
      championship: { id: 9, name: 'Torneo RC' },
    });
    championshipsService.getCategoryOfficialResults.mockResolvedValue({
      league: null,
      cup: { champion: { public_display_name: 'Campeona única' } },
    });

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    expect(await screen.findByRole('article', { name: 'Liga' }))
      .toHaveTextContent('Sin resultado oficial vigente');
    const cup = screen.getByRole('article', { name: 'Copa' });
    expect(cup).toHaveTextContent('Ganador');
    expect(cup).toHaveTextContent('Campeona única');
  });

  it('keeps the category summary when official-results fails independently', async () => {
    championshipsService.getCategory.mockResolvedValue({
      id: 12,
      name: 'Individual absoluta',
      status: 'active',
      championship: { id: 9, name: 'Torneo RC' },
    });
    championshipsService.getCategoryOfficialResults.mockRejectedValue(new Error('Unavailable'));

    renderWithProviders(<CategoryDetail />, {
      route: '/categories/12',
      routePath: '/categories/:categoryId',
    });

    expect(await screen.findByRole('heading', { name: 'Individual absoluta' }))
      .toBeInTheDocument();
    expect(screen.getByText('Datos de la categoría')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent(
      'No se ha podido comprobar el resultado oficial en este momento.',
    );
    expect(screen.queryByText('Todavía no hay resultados oficiales vigentes para esta categoría.'))
      .not.toBeInTheDocument();
  });
});
