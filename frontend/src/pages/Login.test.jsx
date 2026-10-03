import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../test/renderWithProviders';
import Login from './Login';

const renderLogin = (authValue) => renderWithProviders(
  <Routes>
    <Route path="/login" element={<Login />} />
    <Route path="/player" element={<p>Panel del jugador</p>} />
  </Routes>,
  { route: '/login', authValue: { login: vi.fn(), isAuthenticated: false, ...authValue } },
);

describe('Login', () => {
  it('exposes exactly one main heading', () => {
    renderLogin({ authStatus: 'anonymous' });

    expect(screen.getByRole('heading', { name: 'Acceso Jugadores', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });

  it('does not show the credential form while the session is being restored', () => {
    renderLogin({ authStatus: 'restoring' });

    expect(screen.getByRole('status')).toHaveTextContent('Comprobando tu sesión');
    expect(screen.queryByLabelText('Correo Electrónico')).not.toBeInTheDocument();
  });

  it('redirects an already authenticated session to the player area', () => {
    renderLogin({ authStatus: 'authenticated', isAuthenticated: true });

    expect(screen.getByText('Panel del jugador')).toBeInTheDocument();
    expect(screen.queryByLabelText('Correo Electrónico')).not.toBeInTheDocument();
  });

  it('shows a retry, not the form, when the restore failed', async () => {
    const retrySessionRestore = vi.fn();
    renderLogin({ authStatus: 'failed', retrySessionRestore });

    expect(screen.getByRole('alert')).toBeInTheDocument();
    expect(screen.queryByLabelText('Correo Electrónico')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(retrySessionRestore).toHaveBeenCalledOnce();
  });
});
