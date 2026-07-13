import { screen } from '@testing-library/react';
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
  championship: { name: 'Trofeo E2E' },
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
        home_entry: { player: { id: 1, nickname: 'Pilotari Local' } },
        away_entry: { player: { id: 2, nickname: 'Pilotari Visitante' } },
        venue: { id: 31, name: 'Trinquet Central' },
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
        home_entry: { team: { id: 3, name: 'Equip Blau' } },
        away_entry: { team: { id: 4, name: 'Equip Roig' } },
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

    expect(screen.getByRole('status')).toHaveTextContent('Cargando calendario...');
  });

  it('shows a controlled error when the schedule collection cannot be loaded', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockRejectedValue(new Error('Network error'));

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'No se ha podido cargar el calendario' })).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Inténtalo de nuevo más tarde');
    expect(screen.queryByText('No hay jornadas configuradas todavía.')).not.toBeInTheDocument();
  });

  it('shows the empty state for an empty schedule collection', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([]);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Primera E2E' })).toBeInTheDocument();
    expect(screen.getByText('Trofeo E2E')).toBeInTheDocument();
    expect(screen.getByText('No hay jornadas configuradas todavía.')).toBeInTheDocument();
  });

  it('renders the real collection contract with rounds, matches and detail links', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue(schedule);

    renderSchedule();

    expect(await screen.findByRole('heading', { name: 'Primera E2E' })).toBeInTheDocument();
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
    expect(screen.queryByText('No hay jornadas configuradas todavía.')).not.toBeInTheDocument();
    expect(screen.queryByText('99')).not.toBeInTheDocument();
    expect(screen.queryByText('98')).not.toBeInTheDocument();
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

    expect(await screen.findByRole('heading', { name: 'Calendario de la categoría' })).toBeInTheDocument();
    expect(screen.getByText('Campeonato por determinar')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('no se ha podido cargar la información de la categoría');
    expect(screen.getByRole('heading', { name: 'Jornada 1' })).toBeInTheDocument();
    expect(screen.getByText('Fecha por determinar')).toBeInTheDocument();
    expect(screen.getByText('Estado por determinar')).toBeInTheDocument();
    expect(screen.getByText('Pista: Por determinar')).toBeInTheDocument();
    expect(screen.getByText('Detalle no disponible')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Ver partido/ })).not.toBeInTheDocument();
  });
});
