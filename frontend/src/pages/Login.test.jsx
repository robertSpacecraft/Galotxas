import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../test/renderWithProviders';
import Login from './Login';

describe('Login', () => {
  it('exposes exactly one main heading', () => {
    renderWithProviders(<Login />, {
      authValue: { login: vi.fn(), isAuthenticated: false },
    });

    expect(screen.getByRole('heading', { name: 'Acceso Jugadores', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });
});
