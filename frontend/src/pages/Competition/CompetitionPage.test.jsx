import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CompetitionPage } from './CompetitionPage';

describe('CompetitionPage', () => {
  it('provides a minimal accessible hub for the existing competition destinations', () => {
    const { container } = renderWithProviders(<CompetitionPage />, { route: '/competicion' });

    expect(screen.getByRole('heading', { name: 'Competición', level: 1 })).toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(screen.getByRole('navigation', { name: 'Opciones de competición' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Torneos/ })).toHaveAttribute('href', '/torneos');
    expect(screen.getByRole('link', { name: /Rankings/ })).toHaveAttribute('href', '/rankings');
    expect(screen.queryByText(/próximamente|en construcción/i)).not.toBeInTheDocument();
  });
});
