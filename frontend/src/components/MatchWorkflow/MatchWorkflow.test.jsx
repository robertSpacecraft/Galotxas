import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useMatchWorkflow } from '../../hooks/useMatchWorkflow';
import { renderWithProviders } from '../../test/renderWithProviders';
import { MatchWorkflow } from './MatchWorkflow';

vi.mock('../../hooks/useMatchWorkflow', () => ({
  useMatchWorkflow: vi.fn(),
}));

const match = {
  id: 25,
  home_entry: { player: { id: 1, nickname: 'Local' } },
  away_entry: { player: { id: 2, nickname: 'Visitante' } },
};

const createHookState = (overrides = {}) => {
  const { workflow: workflowOverrides, ...stateOverrides } = overrides;
  const baseWorkflow = {
    participates: true,
    can_report: true,
    blocked_reason: null,
    match_status: 'scheduled',
    my_report: null,
    same_side_report_by_teammate: null,
    opposite_report: null,
  };

  return {
    match,
    loading: false,
    actionLoading: false,
    error: null,
    message: null,
    fetchWorkflow: vi.fn(),
    submitResult: vi.fn().mockResolvedValue(true),
    confirmResult: vi.fn().mockResolvedValue(true),
    ...stateOverrides,
    workflow: workflowOverrides
      ? { ...baseWorkflow, ...workflowOverrides }
      : baseWorkflow,
  };
};

describe('MatchWorkflow', () => {
  beforeEach(() => {
    useMatchWorkflow.mockReturnValue(createHookState());
  });

  it('renders the loading state', () => {
    useMatchWorkflow.mockReturnValue(createHookState({ loading: true }));

    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByText('Cargando gestión del resultado...')).toBeInTheDocument();
  });

  it('renders a hook error', () => {
    useMatchWorkflow.mockReturnValue(createHookState({ error: 'Fallo del backend' }));

    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByText('Fallo del backend')).toBeInTheDocument();
  });

  it('informs a non-participant and hides the result form', () => {
    useMatchWorkflow.mockReturnValue(createHookState({
      workflow: { participates: false, can_report: false },
    }));

    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByText(/Solo los participantes del partido/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviar resultado' })).not.toBeInTheDocument();
  });

  it('shows the submission form when the participant can report', () => {
    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByRole('heading', { name: 'Enviar resultado' })).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Local' })).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Visitante' })).toBeInTheDocument();
  });

  it('shows the rival report and confirmation control', () => {
    useMatchWorkflow.mockReturnValue(createHookState({
      workflow: {
        match_status: 'submitted',
        opposite_report: {
          side: 'away',
          home_score: 7,
          away_score: 10,
          status: 'submitted',
          comment: 'Reporte rival',
        },
      },
    }));

    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByRole('heading', { name: 'Reporte de Visitante' })).toBeInTheDocument();
    expect(screen.getByText('Reporte rival')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Confirmar este resultado' })).toBeInTheDocument();
  });

  it('shows under-review and validated states without a form', () => {
    const { rerender } = renderWithProviders(<MatchWorkflow matchId="25" />);

    useMatchWorkflow.mockReturnValue(createHookState({
      workflow: { match_status: 'under_review', can_report: false },
    }));
    rerender(<MatchWorkflow matchId="25" />);

    expect(screen.getByText(/Hay una discrepancia entre reportes/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviar resultado' })).not.toBeInTheDocument();

    useMatchWorkflow.mockReturnValue(createHookState({
      workflow: { match_status: 'validated', can_report: false },
    }));
    rerender(<MatchWorkflow matchId="25" />);

    expect(screen.getByText('Resultado validado oficialmente.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviar resultado' })).not.toBeInTheDocument();
  });

  it('keeps the form hidden when can_report is false', () => {
    useMatchWorkflow.mockReturnValue(createHookState({
      workflow: { can_report: false, blocked_reason: 'match_closed' },
    }));

    renderWithProviders(<MatchWorkflow matchId="25" />);

    expect(screen.getByText('El partido ya está cerrado y no admite nuevos reportes.')).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument();
  });

  it('submits numeric scores and a null optional comment', async () => {
    const user = userEvent.setup();
    const hookState = createHookState();
    useMatchWorkflow.mockReturnValue(hookState);

    renderWithProviders(<MatchWorkflow matchId="25" />);

    await user.type(screen.getByRole('spinbutton', { name: 'Local' }), '10');
    await user.type(screen.getByRole('spinbutton', { name: 'Visitante' }), '7');
    await user.click(screen.getByRole('button', { name: 'Enviar resultado' }));

    await waitFor(() => {
      expect(hookState.submitResult).toHaveBeenCalledWith({
        home_score: 10,
        away_score: 7,
        comment: null,
      });
    });
  });

  it('rejects empty and invalid scores locally', () => {
    const hookState = createHookState();
    useMatchWorkflow.mockReturnValue(hookState);

    renderWithProviders(<MatchWorkflow matchId="25" />);

    const form = screen.getByRole('button', { name: 'Enviar resultado' }).closest('form');
    fireEvent.submit(form);

    expect(screen.getByText('Indica tanteos válidos para ambos participantes.')).toBeInTheDocument();
    expect(hookState.submitResult).not.toHaveBeenCalled();

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Local' }), { target: { value: '-1' } });
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Visitante' }), { target: { value: '7' } });
    fireEvent.submit(form);

    expect(hookState.submitResult).not.toHaveBeenCalled();
  });

  it('confirms the rival report with an optional comment', async () => {
    const user = userEvent.setup();
    const hookState = createHookState({
      workflow: {
        match_status: 'submitted',
        opposite_report: {
          side: 'home',
          home_score: 10,
          away_score: 7,
          status: 'submitted',
        },
      },
    });
    useMatchWorkflow.mockReturnValue(hookState);

    renderWithProviders(<MatchWorkflow matchId="25" />);

    await user.type(screen.getByRole('textbox', { name: 'Comentario opcional' }), 'Confirmado');
    await user.click(screen.getByRole('button', { name: 'Confirmar este resultado' }));

    expect(hookState.confirmResult).toHaveBeenCalledWith('Confirmado');
  });
});
