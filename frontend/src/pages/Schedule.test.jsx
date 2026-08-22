import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../api/championships';
import { renderWithProviders } from '../test/renderWithProviders';
import Schedule from './Schedule';

vi.mock('../api/championships', () => ({
  championshipsService: {
    getCategory: vi.fn(),
    getCategorySchedule: vi.fn(),
  },
}));

const category = {
  id: 12,
  name: 'Primera E2E',
  championship: { name: 'Trofeo E2E', season: { name: 'Temporada E2E' } },
};

const schedule = [
  {
    id: 21,
    name: 'Jornada 1',
    matches: [
      {
        id: 101,
        scheduled_date: '2026-08-01T18:00:00.000Z',
        status: 'validated',
        home_score: 10,
        away_score: 7,
        home_entry: { entry_type: 'player', public_display_name: 'Pilotari Local' },
        away_entry: { entry_type: 'player', public_display_name: 'Pilotari Visitante' },
        venue: { name: 'Trinquet Central' },
      },
    ],
  },
  {
    id: 22,
    name: 'Jornada 2',
    matches: [
      {
        id: 102,
        scheduled_date: '2026-08-02T18:00:00.000Z',
        status: 'scheduled',
        home_score: 99,
        away_score: 98,
        home_entry: { entry_type: 'team', public_display_name: 'Equip Blau' },
        away_entry: { entry_type: 'team', public_display_name: 'Equip Roig' },
        venue: null,
      },
    ],
  },
];

const renderSchedule = () => renderWithProviders(<Schedule />, {
  route: '/categories/12/schedule',
  routePath: '/categories/:categoryId/schedule',
});

describe('Schedule', () => {
  beforeEach(() => {
    championshipsService.getCategory.mockReset();
    championshipsService.getCategorySchedule.mockReset();
  });

  it('shows a loading state while both service requests are pending', () => {
    championshipsService.getCategory.mockReturnValue(new Promise(() => {}));
    championshipsService.getCategorySchedule.mockReturnValue(new Promise(() => {}));

    renderSchedule();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando calendario…');
  });

  it('shows a controlled error when the schedule collection cannot be loaded', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockRejectedValue(new Error('Network error'));

    renderSchedule();

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar el calendario.');
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument();
    expect(screen.queryByText(/Todavía no hay jornadas/)).not.toBeInTheDocument();
  });

  it('shows the empty state for an empty schedule collection', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([]);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Calendario y resultados de Primera E2E' })).toBeInTheDocument();
    expect(screen.getByText('Temporada E2E · Trofeo E2E')).toBeInTheDocument();
    expect(screen.getByText('Todavía no hay jornadas configuradas para esta categoría.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Calendario y resultados' }))
      .toHaveAttribute('aria-current', 'page');
  });

  it('renders the real collection contract with rounds, matches and detail links', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue(schedule);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Calendario y resultados de Primera E2E' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Jornada 1' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Jornada 2' })).toBeInTheDocument();
    expect(screen.getByText('Pilotari Local')).toBeInTheDocument();
    expect(screen.getByText('Pilotari Visitante')).toBeInTheDocument();
    expect(screen.getByText('Equip Blau')).toBeInTheDocument();
    expect(screen.getByText('Equip Roig')).toBeInTheDocument();
    expect(screen.getByText('Pista: Trinquet Central')).toBeInTheDocument();
    expect(screen.getByText('Finalizado')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver partido: Pilotari Local contra Pilotari Visitante' }))
      .toHaveAttribute('href', '/matches/101');
    expect(screen.queryByText(/Todavía no hay jornadas/)).not.toBeInTheDocument();
    expect(screen.queryByText('99')).not.toBeInTheDocument();
    expect(screen.queryByText('98')).not.toBeInTheDocument();
  });

  it('separates cup stages and highlights the official cup champion', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([
      {
        id: 31,
        name: 'Semifinales',
        type: 'cup',
        phase: 'cup',
        stage: 'semifinal',
        matches: [schedule[0].matches[0]],
      },
      {
        id: 32,
        name: 'Final',
        type: 'cup',
        phase: 'cup',
        stage: 'final',
        matches: [{
          ...schedule[0].matches[0],
          id: 110,
          winner_entry: { entry_type: 'player', public_display_name: 'Pilotari Visitante' },
        }],
      },
    ]);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Copa', level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Campeón de Copa').nextElementSibling)
      .toHaveTextContent('Pilotari Visitante');
    expect(screen.getByText('Tercer y cuarto puesto pendiente de generación')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Semifinales', level: 2 })).not.toBeInTheDocument();
  });

  it('keeps the schedule usable with safe fallbacks when context or values are missing', async () => {
    championshipsService.getCategory.mockRejectedValue(new Error('Context unavailable'));
    championshipsService.getCategorySchedule.mockResolvedValue([
      {
        id: 23,
        name: null,
        matches: [{ id: null, status: null, scheduled_date: null, venue: null }],
      },
    ]);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Calendario y resultados de Categoría no disponible' })).toBeInTheDocument();
    expect(screen.getByText('Contexto deportivo no disponible')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('no se ha podido cargar el contexto de la categoría');
    expect(screen.getByRole('heading', { name: 'Jornada 1' })).toBeInTheDocument();
    expect(screen.getByText('Fecha por determinar')).toBeInTheDocument();
    expect(screen.getByText('Estado no disponible')).toBeInTheDocument();
    expect(screen.getByText('Pista: Por determinar')).toBeInTheDocument();
    expect(screen.getByText('Detalle no disponible')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Ver partido/ })).not.toBeInTheDocument();
  });

  it('retries the schedule without losing the loaded category context', async () => {
    const user = userEvent.setup();
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce([]);

    renderSchedule();

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByText('Todavía no hay jornadas configuradas para esta categoría.'))
      .toBeInTheDocument();
    expect(championshipsService.getCategorySchedule).toHaveBeenCalledTimes(2);
  });
});
