import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api, { refreshSpaCsrfToken } from '../api/client';
import {
  AUTH_EVENT_SESSION_CHANGED,
  AUTH_EVENT_SESSION_ENDED,
  AUTH_SESSION_CLEARED_EVENT,
  getCsrfToken,
  isSessionExpected,
  resetSessionState,
  setCsrfToken,
} from '../api/authSession';
import { meService } from '../api/me';
import { useAuth } from '../hooks/useAuth';
import { AuthProvider } from './AuthContext';

vi.mock('../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
  refreshSpaCsrfToken: vi.fn(),
}));

const channels = [];

class FakeBroadcastChannel {
  constructor(name) {
    this.name = name;
    this.onmessage = null;
    this.closed = false;
    this.sent = [];
    channels.push(this);
  }

  postMessage(data) {
    this.sent.push(data);
    channels
      .filter((channel) => channel !== this && !channel.closed && channel.onmessage)
      .forEach((channel) => channel.onmessage({ data }));
  }

  close() {
    this.closed = true;
  }
}

const remoteTab = (type) => {
  const remote = new FakeBroadcastChannel('galotxas-auth');
  remote.postMessage({ type, source: 'remote-tab' });
  remote.close();
};

vi.mock('../api/me', () => ({
  meService: {
    updatePlayerProfile: vi.fn(),
  },
}));

const AuthProbe = () => {
  const [refreshOutcome, setRefreshOutcome] = useState('sin intento');
  const authContext = useAuth();
  const {
    user,
    isAuthenticated,
    login,
    register,
    createPlayerProfile,
    refreshUser,
    logout,
    updatePlayerProfile,
    updateProfilePhoto,
  } = authContext;

  return (
    <>
      <p data-testid="auth-state">{isAuthenticated ? 'autenticada' : 'anónima'}</p>
      <p data-testid="auth-name">{user?.name || 'sin perfil'}</p>
      <p data-testid="refresh-outcome">{refreshOutcome}</p>
      <p data-testid="exposed-keys">{Object.keys(authContext).sort().join(',')}</p>
      <p data-testid="restore-failed">{authContext.sessionRestoreFailed ? 'fallo' : 'ok'}</p>
      <button type="button" onClick={() => login('player@example.test', 'secret')}>Entrar</button>
      <button
        type="button"
        onClick={() => register({ name: 'Nueva', email: 'new@example.test', password: 'secret' })}
      >
        Registrar
      </button>
      <button type="button" onClick={() => authContext.resetPassword({ token: 't' })}>Restablecer</button>
      <button type="button" onClick={() => authContext.retrySessionRestore()}>Reintentar</button>
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
          birth_date: '1990-01-01',
          birth_date_confirmed: true,
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

const sessionPayload = (name, csrf = 'session-csrf') => ({
  data: {
    data: {
      user: { id: 2, name, email: 'login@example.test' },
      player: null,
      csrf_token: csrf,
    },
  },
});

const unauthenticated = () => ({ response: { status: 401, data: { message: 'Unauthenticated.' } } });

describe('AuthProvider cookie-session bootstrap', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    channels.length = 0;
    vi.stubGlobal('BroadcastChannel', FakeBroadcastChannel);
    resetSessionState();
    api.get.mockReset();
    api.post.mockReset();
    refreshSpaCsrfToken.mockReset();
    refreshSpaCsrfToken.mockResolvedValue('primed-csrf');
    meService.updatePlayerProfile.mockReset();
    vi.spyOn(axios, 'post').mockResolvedValue({});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('starts anonymously when /me returns 401, without bootstrapping CSRF', async () => {
    api.get.mockRejectedValue(unauthenticated());

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(api.get).toHaveBeenCalledWith('/me', { sessionMode: true });
    expect(refreshSpaCsrfToken).not.toHaveBeenCalled();
    expect(isSessionExpected()).toBe(false);
  });

  it('restores the user from the cookie through /me and primes CSRF before leaving the loading state', async () => {
    api.get.mockResolvedValue(meResponse('Perfil restaurado'));

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(screen.getByTestId('auth-name')).toHaveTextContent('Perfil restaurado');
    expect(refreshSpaCsrfToken).toHaveBeenCalledOnce();
    expect(isSessionExpected()).toBe(true);
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
  });

  it('exposes no token and derives authentication from the user', async () => {
    api.get.mockResolvedValue(meResponse());

    renderAuthProvider();

    const keys = (await screen.findByTestId('exposed-keys')).textContent.split(',');
    expect(keys).not.toContain('token');
    expect(keys).toContain('user');
  });

  it('cleans legacy storage before the bootstrap and never reuses the legacy token', async () => {
    localStorage.setItem('token', 'legacy-token');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));
    api.get.mockImplementation(() => {
      expect(localStorage).toHaveLength(0);

      return Promise.reject(unauthenticated());
    });

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(axios.post).toHaveBeenCalledOnce();
    expect(axios.post.mock.calls[0][2].headers.Authorization).toBe('Bearer legacy-token');
    expect(JSON.stringify(api.get.mock.calls)).not.toContain('legacy-token');
    expect(localStorage).toHaveLength(0);
  });

  it('keeps loading the app when the legacy logout fails', async () => {
    localStorage.setItem('token', 'legacy-token');
    axios.post.mockRejectedValue(new Error('offline'));
    api.get.mockRejectedValue(unauthenticated());

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(localStorage).toHaveLength(0);
  });

  it('stays anonymous after the inactive-user 403', async () => {
    api.get.mockRejectedValue({
      response: { status: 403, data: { message: 'El usuario está inactivo.' } },
    });

    renderAuthProvider();

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
    expect(refreshSpaCsrfToken).not.toHaveBeenCalled();
    expect(isSessionExpected()).toBe(false);
  });

  it('reports a recoverable failure instead of pretending the server said anonymous on 5xx or network errors', async () => {
    const browserUser = userEvent.setup();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get
      .mockRejectedValueOnce({ response: { status: 500, data: { email: 'private@example.test' } } })
      .mockResolvedValueOnce(meResponse('Recuperada'));

    renderAuthProvider();

    expect(await screen.findByTestId('restore-failed')).toHaveTextContent('fallo');
    expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima');
    expect(consoleError).toHaveBeenCalledWith('No se ha podido restaurar la sesión autenticada.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');

    await browserUser.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(screen.getByTestId('restore-failed')).toHaveTextContent('ok');
  });

  it('logs in through CSRF bootstrap and the session endpoint, storing no credential', async () => {
    const browserUser = userEvent.setup();
    api.get.mockRejectedValue(unauthenticated());
    api.post.mockResolvedValue(sessionPayload('Login correcto', 'login-csrf'));

    renderAuthProvider();
    await browserUser.click(await screen.findByRole('button', { name: 'Entrar' }));

    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(screen.getByTestId('auth-name')).toHaveTextContent('Login correcto');
    expect(refreshSpaCsrfToken).toHaveBeenCalledOnce();
    expect(api.post).toHaveBeenCalledWith(
      '/auth/session/login',
      { email: 'player@example.test', password: 'secret' },
      { sessionMode: true },
    );
    expect(getCsrfToken()).toBe('login-csrf');
    expect(isSessionExpected()).toBe(true);
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
    expect(JSON.stringify(channels.flatMap((channel) => channel.sent))).not.toContain('login-csrf');
  });

  it('registers through the session endpoint and keeps the new CSRF token in memory', async () => {
    const browserUser = userEvent.setup();
    api.get.mockRejectedValue(unauthenticated());
    api.post.mockResolvedValue(sessionPayload('Registrada', 'register-csrf'));

    renderAuthProvider();
    await browserUser.click(await screen.findByRole('button', { name: 'Registrar' }));

    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Registrada');
    expect(api.post).toHaveBeenCalledWith(
      '/auth/session/register',
      { name: 'Nueva', email: 'new@example.test', password: 'secret' },
      { sessionMode: true },
    );
    expect(getCsrfToken()).toBe('register-csrf');
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);
  });

  it('creates the player profile with the registered session', async () => {
    const browserUser = userEvent.setup();
    api.get.mockRejectedValue(unauthenticated());
    api.post
      .mockResolvedValueOnce(sessionPayload('Registrada'))
      .mockResolvedValueOnce({ data: { data: { id: 9, nickname: 'Alias creado' } } });

    renderAuthProvider();
    await browserUser.click(await screen.findByRole('button', { name: 'Registrar' }));
    await screen.findByTestId('auth-name');
    await browserUser.click(screen.getByRole('button', { name: 'Crear perfil' }));

    expect(await screen.findByTestId('player-nickname')).toHaveTextContent('Alias creado');
    expect(api.post.mock.calls[1][0]).toBe('/me/player-profile');
  });

  it('does not store a session when login is rejected', async () => {
    const browserUser = userEvent.setup();
    const failure = { response: { status: 401, data: { message: 'Credenciales incorrectas.' } } };
    api.get.mockRejectedValue(unauthenticated());
    api.post.mockRejectedValue(failure);
    const AttemptProbe = () => {
      const { login } = useAuth();

      return <button type="button" onClick={() => login('a@b.test', 'x').catch(() => {})}>Intentar</button>;
    };

    render(<AuthProvider><AttemptProbe /></AuthProvider>);
    await browserUser.click(await screen.findByRole('button', { name: 'Intentar' }));

    await waitFor(() => expect(api.post).toHaveBeenCalled());
    expect(isSessionExpected()).toBe(false);
    expect(getCsrfToken()).toBeNull();
  });

  it('logs out through the session endpoint, clears memory and notifies other tabs', async () => {
    const browserUser = userEvent.setup();
    api.get.mockResolvedValue(meResponse());
    api.post.mockResolvedValue({ data: { data: { csrf_token: 'after-logout' } } });

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    setCsrfToken('live-csrf');
    await browserUser.click(screen.getByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() => expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima'));
    expect(api.post).toHaveBeenCalledWith('/auth/session/logout', null, { sessionMode: true });
    expect(getCsrfToken()).toBeNull();
    expect(isSessionExpected()).toBe(false);
    expect(channels.flatMap((channel) => channel.sent)).toEqual([
      expect.objectContaining({ type: AUTH_EVENT_SESSION_ENDED }),
    ]);
  });

  it('becomes anonymous when logout finds the session already expired', async () => {
    const browserUser = userEvent.setup();
    api.get.mockResolvedValue(meResponse());
    api.post.mockRejectedValue({ response: { status: 401 } });

    renderAuthProvider();
    await screen.findByTestId('auth-state');
    await browserUser.click(screen.getByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() => expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima'));
  });

  it('does not claim a server logout after a connectivity failure', async () => {
    const browserUser = userEvent.setup();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockResolvedValue(meResponse());
    api.post.mockRejectedValue(new Error('Network Error'));

    renderAuthProvider();
    await screen.findByTestId('auth-state');
    await browserUser.click(screen.getByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() => expect(consoleError).toHaveBeenCalledWith('No se ha podido cerrar la sesión en el servidor.'));
    expect(screen.getByTestId('auth-state')).toHaveTextContent('autenticada');
    expect(isSessionExpected()).toBe(true);
    expect(channels.flatMap((channel) => channel.sent)).toHaveLength(0);
  });

  it.each([401, 419])('becomes anonymous when the API client announces an invalid session (HTTP %s)', async (status) => {
    api.get.mockResolvedValue(meResponse());

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    act(() => {
      window.dispatchEvent(new CustomEvent(AUTH_SESSION_CLEARED_EVENT, { detail: { reason: `http-${status}` } }));
    });

    expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima');
  });

  it('becomes anonymous and drops the session after a password reset', async () => {
    const browserUser = userEvent.setup();
    api.get.mockResolvedValue(meResponse());
    api.post.mockResolvedValue({ data: { message: 'ok' } });

    renderAuthProvider();
    await screen.findByTestId('auth-state');
    await browserUser.click(screen.getByRole('button', { name: 'Restablecer' }));

    await waitFor(() => expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima'));
    expect(api.post).toHaveBeenCalledWith('/auth/reset-password', { token: 't' });
    expect(refreshSpaCsrfToken).toHaveBeenCalledTimes(1);
  });

  describe('cross-tab synchronization', () => {
    it('becomes anonymous when another tab ends the session, without rebroadcasting', async () => {
      api.get.mockResolvedValue(meResponse());

      renderAuthProvider();
      expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
      act(() => remoteTab(AUTH_EVENT_SESSION_ENDED));

      expect(screen.getByTestId('auth-state')).toHaveTextContent('anónima');
      expect(isSessionExpected()).toBe(false);
      expect(channels.flatMap((channel) => channel.sent).filter((m) => m.source !== 'remote-tab')).toHaveLength(0);
    });

    it('re-bootstraps /me when another tab changes the session, reflecting the new account', async () => {
      api.get
        .mockRejectedValueOnce(unauthenticated())
        .mockResolvedValueOnce(meResponse('Otra cuenta'));

      renderAuthProvider();
      expect(await screen.findByTestId('auth-state')).toHaveTextContent('anónima');
      act(() => remoteTab(AUTH_EVENT_SESSION_CHANGED));

      expect(await screen.findByTestId('auth-name')).toHaveTextContent('Otra cuenta');
      expect(api.get).toHaveBeenCalledTimes(2);
      expect(channels.flatMap((channel) => channel.sent).filter((m) => m.source !== 'remote-tab')).toHaveLength(0);
    });

    it('closes its channel on unmount', async () => {
      api.get.mockRejectedValue(unauthenticated());

      const { unmount } = renderAuthProvider();
      await screen.findByTestId('auth-state');
      const open = channels.filter((channel) => !channel.closed);
      expect(open.length).toBeGreaterThan(0);
      unmount();

      expect(channels.every((channel) => channel.closed)).toBe(true);
    });
  });

  it('refreshes the in-memory profile without adding browser storage', async () => {
    const browserUser = userEvent.setup();
    api.get
      .mockResolvedValueOnce(meResponse('Perfil inicial'))
      .mockResolvedValueOnce(meResponse('Perfil actualizado'));

    renderAuthProvider();
    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil inicial');
    await browserUser.click(screen.getByRole('button', { name: 'Refrescar' }));

    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil actualizado');
    expect(localStorage).toHaveLength(0);
  });

  it('updates only the in-memory profile photo projection', async () => {
    const browserUser = userEvent.setup();
    api.get.mockResolvedValue(meResponse('Perfil con foto'));

    renderAuthProvider();
    expect(await screen.findByTestId('auth-state')).toHaveTextContent('autenticada');
    await browserUser.click(screen.getByRole('button', { name: 'Actualizar foto' }));

    expect(screen.getByTestId('profile-photo')).toHaveTextContent('private-stable-url');
    expect(localStorage).toHaveLength(0);
  });

  it('updates the player projection and refreshes the full private account context', async () => {
    const browserUser = userEvent.setup();
    api.get
      .mockResolvedValueOnce(meResponse('Perfil inicial'))
      .mockResolvedValueOnce(meResponse('Perfil refrescado'));
    meService.updatePlayerProfile.mockResolvedValue({ id: 7, nickname: 'Alias nuevo' });

    renderAuthProvider();
    expect(await screen.findByTestId('auth-name')).toHaveTextContent('Perfil inicial');
    await browserUser.click(screen.getByRole('button', { name: 'Actualizar perfil' }));

    await waitFor(() => expect(screen.getByTestId('auth-name')).toHaveTextContent('Perfil refrescado'));
    expect(meService.updatePlayerProfile).toHaveBeenCalledWith({ nickname: 'Alias nuevo' });
  });

  it('marks the general declaration as recognized after successful player creation', async () => {
    const browserUser = userEvent.setup();
    const creationPayload = {
      birth_date: '1990-01-01',
      birth_date_confirmed: true,
      profile_declaration_accepted: true,
      profile_notice_id: 'NOTICE-ACCOUNT-PROFILE',
      profile_notice_version: '1.0.0',
    };
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
    expect(isSessionExpected()).toBe(true);
    expect(consoleError).toHaveBeenCalledWith('No se han podido actualizar los datos de la cuenta.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');
  });
});

describe('AuthProvider restoration state', () => {
  const StatusProbe = () => {
    const { authStatus, retrySessionRestore } = useAuth();

    return (
      <>
        <p data-testid="public-content">contenido público</p>
        <p data-testid="status">{authStatus}</p>
        <button type="button" onClick={() => retrySessionRestore()}>Reintentar</button>
      </>
    );
  };

  beforeEach(() => {
    localStorage.clear();
    resetSessionState();
    api.get.mockReset();
    refreshSpaCsrfToken.mockReset();
    refreshSpaCsrfToken.mockResolvedValue('csrf');
    vi.stubGlobal('BroadcastChannel', undefined);
    vi.spyOn(axios, 'post').mockResolvedValue({});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  const renderStatus = () => render(<AuthProvider><StatusProbe /></AuthProvider>);

  it('renders public content while /me is pending and settles to the confirmed result', async () => {
    let rejectMe;
    api.get.mockReturnValue(new Promise((_, reject) => { rejectMe = reject; }));

    renderStatus();

    expect(screen.getByTestId('public-content')).toBeInTheDocument();
    expect(screen.getByTestId('status')).toHaveTextContent('restoring');

    await act(async () => rejectMe({ response: { status: 401 } }));

    expect(screen.getByTestId('status')).toHaveTextContent('anonymous');
  });

  it('reports failed (not anonymous) on a 5xx and recovers to authenticated on retry', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const browserUser = userEvent.setup();
    api.get
      .mockRejectedValueOnce({ response: { status: 503 } })
      .mockResolvedValueOnce(meResponse('Recuperada'));

    renderStatus();

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('failed'));
    await browserUser.click(screen.getByRole('button', { name: 'Reintentar' }));

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'));
  });

  it('recovers from a network failure to confirmed anonymous on retry', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const browserUser = userEvent.setup();
    api.get
      .mockRejectedValueOnce(new Error('Network Error'))
      .mockRejectedValueOnce({ response: { status: 401 } });

    renderStatus();

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('failed'));
    await browserUser.click(screen.getByRole('button', { name: 'Reintentar' }));

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('anonymous'));
  });
});
