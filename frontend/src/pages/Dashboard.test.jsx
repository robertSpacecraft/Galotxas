import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { meService } from '../api/me';
import { matchesService } from '../api/matches';
import { useAuth } from '../hooks/useAuth';
import Dashboard from './Dashboard';

vi.mock('../hooks/useAuth', () => ({
  useAuth: vi.fn(),
}));

vi.mock('../api/me', () => ({
  meService: {
    getRegistrations: vi.fn(),
    getMatches: vi.fn(),
    getCalendar: vi.fn(),
    getRankings: vi.fn(),
  },
}));

vi.mock('../api/matches', () => ({
  matchesService: {
    getPendingActions: vi.fn(),
  },
}));

const playerUser = {
  name: 'Player',
  lastname: 'E2E',
  email: 'player@example.test',
  role: 'user',
  profile_photo: null,
  player: {
    nickname: 'Pilotari',
    dni: null,
    gender: 'other',
    level: 3,
    dominant_hand: 'right',
    license_number: 'E2E-1',
    notes: null,
  },
};

const renderPlayerDashboard = () => {
  const refreshUser = vi.fn().mockResolvedValue(playerUser);

  useAuth.mockReturnValue({
    user: playerUser,
    createPlayerProfile: vi.fn(),
    refreshUser,
    updateProfilePhoto: vi.fn(),
  });

  render(
    <MemoryRouter>
      <Dashboard />
    </MemoryRouter>,
  );

  return { refreshUser };
};

describe('Dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    meService.getRegistrations.mockResolvedValue([]);
    meService.getMatches.mockResolvedValue([]);
    meService.getCalendar.mockResolvedValue([]);
    meService.getRankings.mockResolvedValue([]);
    matchesService.getPendingActions.mockResolvedValue([]);
  });

  it('does not log again when AuthContext has already handled a failed initial refresh', async () => {
    const refreshUser = vi.fn().mockRejectedValue({ response: { status: 401 } });
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    useAuth.mockReturnValue({
      user: {
        name: 'Player',
        lastname: 'E2E',
        email: 'player@example.test',
        role: 'user',
      },
      createPlayerProfile: vi.fn(),
      refreshUser,
      updateProfilePhoto: vi.fn(),
    });

    render(
      <MemoryRouter>
        <Dashboard />
      </MemoryRouter>,
    );

    expect(screen.getByRole('heading', { name: 'Panel de Control', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Foto de perfil', level: 3 })).toBeInTheDocument();
    await waitFor(() => expect(refreshUser).toHaveBeenCalledOnce());
    expect(consoleError).not.toHaveBeenCalled();
    consoleError.mockRestore();
  });

  it('exposes five related tabs and selects Resumen initially', async () => {
    renderPlayerDashboard();

    expect(screen.getByRole('tablist', { name: 'Secciones de Mi Panel' }))
      .toBeInTheDocument();

    const tabs = screen.getAllByRole('tab');
    expect(tabs.map((tab) => tab.textContent)).toEqual([
      'Resumen',
      'Mis Inscripciones',
      'Mis Partidos',
      'Calendario',
      'Rankings',
    ]);

    const summaryTab = screen.getByRole('tab', { name: 'Resumen' });
    expect(summaryTab).toHaveAttribute('aria-selected', 'true');
    expect(summaryTab).toHaveAttribute('tabindex', '0');

    for (const tab of tabs.slice(1)) {
      expect(tab).toHaveAttribute('aria-selected', 'false');
      expect(tab).toHaveAttribute('tabindex', '-1');
    }

    const visiblePanel = screen.getByRole('tabpanel');
    expect(visiblePanel).toHaveAttribute('id', summaryTab.getAttribute('aria-controls'));
    expect(visiblePanel).toHaveAttribute('aria-labelledby', summaryTab.id);

    for (const tab of tabs) {
      expect(document.getElementById(tab.getAttribute('aria-controls'))).toBeInTheDocument();
    }

    expect(screen.getByRole('heading', { name: 'Datos de Usuario' })).toBeInTheDocument();
    await waitFor(() => expect(matchesService.getPendingActions).toHaveBeenCalledOnce());
  });

  it('moves focus with arrows and Home/End without activating or loading panels', async () => {
    const user = userEvent.setup();
    renderPlayerDashboard();

    const summaryTab = screen.getByRole('tab', { name: 'Resumen' });
    const registrationsTab = screen.getByRole('tab', { name: 'Mis Inscripciones' });
    const rankingsTab = screen.getByRole('tab', { name: 'Rankings' });

    summaryTab.focus();
    await user.keyboard('{ArrowRight}');
    expect(registrationsTab).toHaveFocus();
    expect(registrationsTab).toHaveAttribute('tabindex', '0');
    expect(summaryTab).toHaveAttribute('aria-selected', 'true');
    expect(meService.getRegistrations).not.toHaveBeenCalled();

    await user.keyboard('{ArrowLeft}');
    expect(summaryTab).toHaveFocus();
    await user.keyboard('{ArrowLeft}');
    expect(rankingsTab).toHaveFocus();
    expect(summaryTab).toHaveAttribute('aria-selected', 'true');
    expect(meService.getRankings).not.toHaveBeenCalled();

    await user.keyboard('{Home}');
    expect(summaryTab).toHaveFocus();
    await user.keyboard('{End}');
    expect(rankingsTab).toHaveFocus();
    expect(summaryTab).toHaveAttribute('aria-selected', 'true');
    expect(meService.getRankings).not.toHaveBeenCalled();
  });

  it('activates tabs with click, Enter and Space while preserving lazy loading', async () => {
    const user = userEvent.setup();
    renderPlayerDashboard();

    const registrationsTab = screen.getByRole('tab', { name: 'Mis Inscripciones' });
    await user.click(registrationsTab);
    expect(registrationsTab).toHaveAttribute('aria-selected', 'true');
    await waitFor(() => expect(meService.getRegistrations).toHaveBeenCalledOnce());
    expect(await screen.findByText('Aún no te has inscrito a ningún torneo.')).toBeInTheDocument();

    const matchesTab = screen.getByRole('tab', { name: 'Mis Partidos' });
    matchesTab.focus();
    await user.keyboard('{Enter}');
    expect(matchesTab).toHaveAttribute('aria-selected', 'true');
    await waitFor(() => expect(meService.getMatches).toHaveBeenCalledOnce());
    expect(await screen.findByText('No tienes partidos registrados todavía.')).toBeInTheDocument();

    const calendarTab = screen.getByRole('tab', { name: 'Calendario' });
    calendarTab.focus();
    await user.keyboard(' ');
    expect(calendarTab).toHaveAttribute('aria-selected', 'true');
    await waitFor(() => expect(meService.getCalendar).toHaveBeenCalledOnce());
    expect(await screen.findByText('No tienes eventos próximos en el calendario.')).toBeInTheDocument();

    const rankingsTab = screen.getByRole('tab', { name: 'Rankings' });
    await user.click(rankingsTab);
    expect(rankingsTab).toHaveAttribute('aria-selected', 'true');
    await waitFor(() => expect(meService.getRankings).toHaveBeenCalledOnce());
    expect(await screen.findByText(/No tienes datos de ranking registrados/)).toBeInTheDocument();
  });

  it('keeps loading, empty and error states available inside the active panel', async () => {
    const user = userEvent.setup();
    let resolveRegistrations;
    meService.getRegistrations.mockImplementation(() => new Promise((resolve) => {
      resolveRegistrations = resolve;
    }));
    meService.getMatches.mockRejectedValue(new Error('Unavailable'));

    renderPlayerDashboard();

    await user.click(screen.getByRole('tab', { name: 'Mis Inscripciones' }));
    expect(await screen.findByText('Cargando inscripciones...')).toBeInTheDocument();
    resolveRegistrations([]);
    expect(await screen.findByText('Aún no te has inscrito a ningún torneo.')).toBeInTheDocument();

    await user.click(screen.getByRole('tab', { name: 'Mis Partidos' }));
    expect(await screen.findByText('No se pudieron cargar tus partidos en este momento.'))
      .toBeInTheDocument();
  });
});
