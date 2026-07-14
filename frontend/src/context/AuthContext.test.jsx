import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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
  const { refreshUser, logout } = useAuth();

  return (
    <>
      <button type="button" onClick={() => refreshUser().catch(() => {})}>Refrescar</button>
      <button type="button" onClick={logout}>Cerrar sesión</button>
    </>
  );
};

const renderAuthProvider = () => (
  <AuthProvider>
    <AuthProbe />
  </AuthProvider>
);

describe('AuthProvider invalid sessions', () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem('token', 'invalid-token');
    localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Player' }));
    api.get.mockReset();
    api.post.mockReset();
  });

  it('clears a 401 session once without logging an expected error', async () => {
    const user = userEvent.setup();
    const listener = vi.fn();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockRejectedValue({ response: { status: 401 } });
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    render(renderAuthProvider());
    await user.click(await screen.findByRole('button', { name: 'Refrescar' }));

    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
    expect(listener).toHaveBeenCalledOnce();
    expect(consoleError).not.toHaveBeenCalled();
    expect(api.post).not.toHaveBeenCalled();

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });

  it('keeps unexpected server errors visible without clearing the session', async () => {
    const user = userEvent.setup();
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockRejectedValue({ response: { status: 500 } });

    render(renderAuthProvider());
    await user.click(await screen.findByRole('button', { name: 'Refrescar' }));

    expect(localStorage.getItem('token')).toBe('invalid-token');
    expect(consoleError).toHaveBeenCalledOnce();
  });

  it('keeps manual logout revocation and local cleanup', async () => {
    const user = userEvent.setup();
    api.post.mockResolvedValue({});

    render(renderAuthProvider());
    await user.click(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    expect(api.post).toHaveBeenCalledWith('/auth/logout');
    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
  });
});
