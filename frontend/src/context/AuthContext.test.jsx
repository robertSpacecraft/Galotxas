import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../api/client';
import { AUTH_SESSION_CLEARED_EVENT } from '../api/authSession';
import { meService } from '../api/me';
import { useAuth } from '../hooks/useAuth';
import { AuthProvider } from './AuthContext';

vi.mock('../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

vi.mock('../api/me', () => ({
  meService: {
    updatePlayerProfile: vi.fn(),
  },
}));

const AuthProbe = () => {
  const [refreshOutcome, setRefreshOutcome] = useState('sin intento');
  const {
    user,
    isAuthenticated,
    login,
    createPlayerProfile,
    refreshUser,
    logout,
    updatePlayerProfile,
    updateProfilePhoto,
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
      <button type="button" onClick={() => updateProfilePhoto({ url: 'private-stable-url' })}>
        Actualizar foto
      </button>
      <button type="button" onClick={() => updatePlayerProfile({ nickname: 'Alias nuevo' })}>
        Actualizar perfil
      </button>
      <button
        type="button"
        onClick={() => createPlayerProfile({
          level: 5,
          profile_declaration_accepted: true,
          profile_notice_id: 'NOTICE-ACCOUNT-PROFILE',
          profile_notice_version: '1.0.0',
        })}
      >
        Crear perfil
      </button>
      <p data-testid="profile-photo">{user?.profile_photo?.url || 'sin foto'}</p>
      <p data-testid="player-nickname">{user?.player?.nickname || 'sin jugador'}</p>
      <p data-testid="profile-declaration-state">
        {user?.profile_declaration_required ? 'requerida' : 'reconocida'}
      </p>
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
    meService.updatePlayerProfile.mockReset();
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

  it('updates only the in-memory profile photo projection', async () => {
    const browserUser = userEvent.setup();
    localStorage.setItem('token', 'stored-token');
    api.get.mockResolvedValue(meResponse('Perfil con foto'));

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    await browserUser.click(screen.getByRole('button', { name: 'Actualizar foto' }));

    expect(screen.getByTestId('profile-photo')).toHaveTextContent('private-stable-url');
    expect(localStorage.getItem('user')).toBeNull();
    expect(localStorage).toHaveLength(1);
  });

  it('updates the player projection and refreshes the full private account context', async () => {
    const browserUser = userEvent.setup();
    localStorage.setItem('token', 'stored-token');
    api.get
      .mockResolvedValueOnce(meResponse('Perfil inicial'))
      .mockResolvedValueOnce(meResponse('Perfil refrescado'));
    meService.updatePlayerProfile.mockResolvedValue({ id: 7, nickname: 'Alias nuevo' });

    renderAuthProvider();
    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil inicial');
    await browserUser.click(screen.getByRole('button', { name: 'Actualizar perfil' }));

    await waitFor(() => expect(screen.getByTestId('auth-name')).toHaveTextContent('Perfil refrescado'));
    expect(meService.updatePlayerProfile).toHaveBeenCalledWith({ nickname: 'Alias nuevo' });
    expect(api.get).toHaveBeenCalledTimes(2);
    expect(localStorage.getItem('user')).toBeNull();
  });

  it('marks the general declaration as recognized after successful player creation', async () => {
    const browserUser = userEvent.setup();
    const creationPayload = {
      level: 5,
      profile_declaration_accepted: true,
      profile_notice_id: 'NOTICE-ACCOUNT-PROFILE',
      profile_notice_version: '1.0.0',
    };
    localStorage.setItem('token', 'stored-token');
    api.get.mockResolvedValue({
      data: {
        data: {
          user: {
            id: 1,
            name: 'Perfil heredado',
            role: 'user',
            profile_declaration_required: true,
          },
          player: null,
        },
      },
    });
    api.post.mockResolvedValue({
      data: {
        data: { id: 7, nickname: 'Alias creado' },
      },
    });

    renderAuthProvider();
    expect(await screen.findByTestId('profile-declaration-state')).toHaveTextContent('requerida');
    expect(screen.getByTestId('player-nickname')).toHaveTextContent('sin jugador');
    await browserUser.click(screen.getByRole('button', { name: 'Crear perfil' }));

    expect(await screen.findByTestId('player-nickname')).toHaveTextContent('Alias creado');
    expect(screen.getByTestId('profile-declaration-state')).toHaveTextContent('reconocida');
    expect(api.post).toHaveBeenCalledWith('/me/player-profile', creationPayload);
    expect(api.get).toHaveBeenCalledOnce();
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
