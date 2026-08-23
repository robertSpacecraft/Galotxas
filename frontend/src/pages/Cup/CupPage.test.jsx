import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../../api/championships';
import { renderWithProviders } from '../../test/renderWithProviders';
import CupPage from './CupPage';

vi.mock('../../api/championships', () => ({
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

const entry = (name) => ({ entry_type: 'player', public_display_name: name });

const cupMatch = (id, overrides = {}) => ({
  id,
  scheduled_date: null,
  status: 'scheduled',
  home_score: null,
  away_score: null,
  home_entry: entry(`Local ${id}`),
  away_entry: entry(`Visitante ${id}`),
  winner_entry: null,
  venue: null,
  ...overrides,
});

const cupRound = (id, stage, matches) => ({
  id,
  name: stage,
  type: 'cup',
  phase: 'cup',
  stage,
  matches,
});

const semifinalRound = (matches = [cupMatch(51), cupMatch(52)]) => (
  cupRound(31, 'semifinal', matches)
);

const renderCup = () => renderWithProviders(<CupPage />, {
  route: '/categories/12/cup',
  routePath: '/categories/:categoryId/cup',
});

describe('CupPage', () => {
  beforeEach(() => {
    championshipsService.getCategory.mockReset();
    championshipsService.getCategorySchedule.mockReset();
  });

  it('shows an accessible loading state while category and schedule are pending', () => {
    championshipsService.getCategory.mockReturnValue(new Promise(() => {}));
    championshipsService.getCategorySchedule.mockReturnValue(new Promise(() => {}));

    renderCup();

    expect(screen.getByRole('status')).toHaveTextContent('Cargando Copa…');
  });

  it('shows a controlled error and retries the same public schedule source', async () => {
    const user = userEvent.setup();
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce([]);

    renderCup();

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar la Copa.');
    await user.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByText('Todavía no hay una Copa generada para esta categoría.'))
      .toBeInTheDocument();
    expect(championshipsService.getCategorySchedule).toHaveBeenCalledTimes(2);
  });

  it('shows the neutral empty state without suggesting an error or a future date', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([]);

    renderCup();

    expect(await screen.findByRole('heading', { name: 'Copa de Primera E2E', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByText('Temporada E2E · Trofeo E2E')).toBeInTheDocument();
    expect(screen.getByText('Todavía no hay una Copa generada para esta categoría.'))
      .toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('renders both generated semifinals and marks Copa as the current category view', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([semifinalRound()]);

    renderCup();

    expect(await screen.findByRole('heading', { name: 'Semifinal 1', level: 4 }))
      .toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Semifinal 2', level: 4 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Copa' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Calendario y resultados' }))
      .not.toHaveAttribute('aria-current');
  });

  it('shows one official semifinal result without inventing the pending final', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([semifinalRound([
      cupMatch(51, {
        status: 'validated',
        home_score: 10,
        away_score: 7,
        winner_entry: entry('Ganador primera semifinal'),
      }),
      cupMatch(52),
    ])]);

    renderCup();

    expect(await screen.findByText(/Ganador:/)).toHaveTextContent('Ganador primera semifinal');
    expect(screen.getByText('Final pendiente de generación')).toBeInTheDocument();
    expect(screen.queryByText('Campeón de Copa')).not.toBeInTheDocument();
  });

  it('keeps the final pending after both semifinals are validated but not generated', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([semifinalRound([
      cupMatch(51, {
        status: 'validated',
        home_score: 10,
        away_score: 7,
        winner_entry: entry('Finalista uno'),
      }),
      cupMatch(52, {
        status: 'validated',
        home_score: 8,
        away_score: 10,
        winner_entry: entry('Finalista dos'),
      }),
    ])]);

    renderCup();

    expect(await screen.findAllByText(/Ganador:/)).toHaveLength(2);
    expect(screen.getByText('Final pendiente de generación')).toBeInTheDocument();
    expect(screen.queryByText('Campeón de Copa')).not.toBeInTheDocument();
  });

  it('renders generated final and third-place stages after the semifinals', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([
      semifinalRound(),
      cupRound(32, 'final', [cupMatch(60)]),
      cupRound(33, 'third_place', [cupMatch(61)]),
    ]);

    renderCup();

    expect(await screen.findByRole('heading', { name: 'Final', level: 3 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Tercer y cuarto puesto', level: 3 }))
      .toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver detalle de Final' }))
      .toHaveAttribute('href', '/matches/60');
  });

  it('announces the official champion only from a validated final winner_entry', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([
      semifinalRound(),
      cupRound(32, 'final', [cupMatch(60, {
        status: 'validated',
        home_score: 5,
        away_score: 10,
        winner_entry: entry('Campeona oficial'),
      })]),
      cupRound(33, 'third_place', [cupMatch(61)]),
    ]);

    renderCup();

    expect(await screen.findByText('Campeón de Copa')).toBeInTheDocument();
    expect(screen.getByText('Campeón de Copa').nextElementSibling)
      .toHaveTextContent('Campeona oficial');
  });

  it('does not infer legacy cup rounds from their name or order', async () => {
    championshipsService.getCategory.mockResolvedValue(category);
    championshipsService.getCategorySchedule.mockResolvedValue([{
      id: 90,
      name: 'Final Copa histórica',
      type: 'cup',
      phase: null,
      stage: null,
      order: 99,
      matches: [cupMatch(90, { home_entry: entry('Participante legado') })],
    }]);

    renderCup();

    expect(await screen.findByText('Todavía no hay una Copa generada para esta categoría.'))
      .toBeInTheDocument();
    expect(screen.queryByText('Participante legado')).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Final Copa histórica' })).not.toBeInTheDocument();
  });
});
