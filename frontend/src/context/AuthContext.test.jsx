import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../api/client';
import { AUTH_SESSION_CLEARED_EVENT } from '../api/authSession';
import { useAuth } from '../hooks/useAuth';
import { AuthProvider } from './AuthContext';

vi.mock('../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const AuthProbe = () => {
  const [refreshOutcome, setRefreshOutcome] = useState('sin intento');
  const {
    user,
    isAuthenticated,
    login,
    refreshUser,
    logout,
  } = useAuth();

  return (
    <>
      <p data-testid="auth-state">{isAuthenticated ? 'autenticada' : 'anónima'}</p>
      <p data-testid="auth-name">{user?.name || 'sin perfil'}</p>
      <p data-testid="refresh-outcome">{refreshOutcome}</p>
      <button type="button" onClick={() => login('player@example.test', 'secret')}>Entrar</button>
      <button
        type="button"
        onClick={() => refreshUser()
          .then(() => setRefreshOutcome('actualizada'))
          .catch((error) => setRefreshOutcome(`error-${error.response?.status || 'desconocido'}`))}
      >
        Refrescar
      </button>
      <button type="button" onClick={logout}>Cerrar sesión</button>
    </>
  );
};

const renderAuthProvider = () => render(
  <AuthProvider>
    <AuthProbe />
  </AuthProvider>
);

const meResponse = (name = 'Player') => ({
  data: {
    data: {
      user: { id: 1, name, role: 'user', email: 'private@example.test' },
      player: { id: 7, nickname: 'Alias privado' },
    },
  },
});

describe('AuthProvider storage and bootstrap', () => {
  beforeEach(() => {
    localStorage.clear();
    api.get.mockReset();
    api.post.mockReset();
  });

  it('starts anonymously and deletes a legacy stored profile', async () => {
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(localStorage.getItem('user')).toBeNull();
    expect(api.get).not.toHaveBeenCalled();
  });

  it('restores a token session through /me without persisting the profile', async () => {
    localStorage.setItem('token', 'stored-token');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));
    api.get.mockResolvedValue(meResponse('Perfil restaurado'));

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(screen.getByTestId('auth-name')).toHaveTextContent('Perfil restaurado');
    expect(api.get).toHaveBeenCalledWith('/me');
    expect(localStorage.getItem('token')).toBe('stored-token');
    expect(localStorage.getItem('user')).toBeNull();
    expect(localStorage).toHaveLength(1);
  });

  it('stores only the bearer token after login', async () => {
    const browserUser = userEvent.setup();
    api.post.mockResolvedValue({
      data: {
        data: {
          token: 'login-token',
          user: { id: 2, name: 'Login correcto', email: 'login@example.test' },
          player: null,
        },
      },
    });

    renderAuthProvider();
    await browserUser.click(await screen.findByRole('button', { name: 'Entrar' }));

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(localStorage.getItem('token')).toBe('login-token');
    expect(localStorage.getItem('user')).toBeNull();
    expect(localStorage).toHaveLength(1);
    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'player@example.test',
      password: 'secret',
    });
  });

  it.each([401, 419])('clears an invalid bootstrap session on HTTP %s without logging response data', async (status) => {
    const listener = vi.fn();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    localStorage.setItem('token', 'invalid-token');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));
    api.get.mockRejectedValue({ response: { status, data: { email: 'private@example.test' } } });
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(localStorage).toHaveLength(0);
    expect(listener).toHaveBeenCalledOnce();
    expect(consoleError).not.toHaveBeenCalled();

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });

  it('clears the bootstrap token when Laravel reports an inactive user', async () => {
    localStorage.setItem('token', 'revoked-token');
    api.get.mockRejectedValue({
      response: { status: 403, data: { message: 'El usuario está inactivo.' } },
    });

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(localStorage).toHaveLength(0);
  });

  it('keeps the token after an unexpected bootstrap error and logs no private payload', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    localStorage.setItem('token', 'retryable-token');
    api.get.mockRejectedValue({
      response: { status: 500, data: { email: 'private@example.test' } },
    });

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(localStorage.getItem('token')).toBe('retryable-token');
    expect(consoleError).toHaveBeenCalledWith('No se ha podido restaurar la sesión autenticada.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');
  });

  it('refreshes the in-memory profile without adding browser storage', async () => {
    const browserUser = userEvent.setup();
    localStorage.setItem('token', 'stored-token');
    api.get
      .mockResolvedValueOnce(meResponse('Perfil inicial'))
      .mockResolvedValueOnce(meResponse('Perfil actualizado'));

    renderAuthProvider();
    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil inicial');
    await browserUser.click(screen.getByRole('button', { name: 'Refrescar' }));

    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil actualizado');
    expect(localStorage.getItem('user')).toBeNull();
    expect(localStorage).toHaveLength(1);
  });

  it('preserves the account and propagates an ordinary 403 during refresh', async () => {
    const browserUser = userEvent.setup();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    localStorage.setItem('token', 'stored-token');
    api.get
      .mockResolvedValueOnce(meResponse('Perfil autorizado'))
      .mockRejectedValueOnce({
        response: {
          status: 403,
          data: { message: 'No tienes permiso.', email: 'private@example.test' },
        },
      });

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    await browserUser.click(screen.getByRole('button', { name: 'Refrescar' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledTimes(2));
    expect(screen.getByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(screen.getByTestId('auth-name')).toHaveTextContent('Perfil autorizado');
    expect(await screen.findByTestId('refresh-outcome')).toHaveTextContent('error-403');
    expect(localStorage.getItem('token')).toBe('stored-token');
    expect(consoleError).toHaveBeenCalledWith('No se han podido actualizar los datos de la cuenta.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');
  });

  it('revokes the current token and clears local state on logout', async () => {
    const browserUser = userEvent.setup();
    localStorage.setItem('token', 'stored-token');
    api.get.mockResolvedValue(meResponse());
    api.post.mockResolvedValue({});

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    await browserUser.click(screen.getByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() => expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima'));
    expect(api.post).toHaveBeenCalledWith('/auth/logout');
    expect(localStorage).toHaveLength(0);
  });
});
