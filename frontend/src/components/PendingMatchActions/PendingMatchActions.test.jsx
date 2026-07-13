import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { PendingMatchActions } from './PendingMatchActions';

const createMatch = (id, overrides = {}) => ({
  id,
  scheduled_date: '2026-07-15T18:30:00.000Z',
  home_entry: { player: { nickname: `Local ${id}` } },
  away_entry: { player: { nickname: `Visitante ${id}` } },
  round: {
    category: {
      name: 'Primera',
      championship: { name: 'Trofeu d’Estiu' },
    },
  },
  ...overrides,
});

describe('PendingMatchActions', () => {
  it('renders its loading state', () => {
    renderWithProviders(<PendingMatchActions actions={[]} loading error={null} />);

    expect(screen.getByRole('status')).toHaveTextContent('Cargando acciones pendientes...');
  });

  it('renders its error state without the empty state', () => {
    renderWithProviders(
      <PendingMatchActions actions={[]} loading={false} error="No se pudo cargar" />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('No se pudo cargar');
    expect(screen.queryByText('No tienes acciones pendientes.')).not.toBeInTheDocument();
  });

  it('renders its empty state and zero counter', () => {
    renderWithProviders(<PendingMatchActions actions={[]} loading={false} error={null} />);

    expect(screen.getByText('No tienes acciones pendientes.')).toBeInTheDocument();
    expect(screen.getByLabelText('0 acciones pendientes')).toHaveTextContent('0');
  });

  it('renders all action types, match information and workflow links', () => {
    const actions = [
      { type: 'submit_result', match: createMatch(11) },
      { type: 'confirm_result', match: createMatch(12) },
      { type: 'under_review', match: createMatch(13) },
    ];

    renderWithProviders(<PendingMatchActions actions={actions} loading={false} error={null} />);

    expect(screen.getByLabelText('3 acciones pendientes')).toHaveTextContent('3');
    expect(screen.getAllByText('Enviar resultado')).toHaveLength(2);
    expect(screen.getAllByText('Confirmar resultado')).toHaveLength(2);
    expect(screen.getByText('Resultado en revisión')).toBeInTheDocument();
    expect(screen.getByText(/Local 11/)).toHaveTextContent('Local 11 vs Visitante 11');
    expect(screen.getAllByText('Trofeu d’Estiu · Primera')).toHaveLength(3);
    expect(screen.getAllByText(/15 jul 2026/i)).toHaveLength(3);
    expect(screen.getByRole('link', { name: 'Enviar resultado' })).toHaveAttribute('href', '/matches/11');
    expect(screen.getByRole('link', { name: 'Confirmar resultado' })).toHaveAttribute('href', '/matches/12');
    expect(screen.getByRole('link', { name: 'Ver revisión' })).toHaveAttribute('href', '/matches/13');

    const reviewCard = screen.getByText('Resultado en revisión').closest('article');
    expect(within(reviewCard).queryByRole('button')).not.toBeInTheDocument();
    expect(within(reviewCard).queryByRole('link', { name: /Enviar|Confirmar/ })).not.toBeInTheDocument();
  });
});
