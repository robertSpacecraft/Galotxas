import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AUTH_SESSION_CLEARED_EVENT,
  clearAuthSession,
  clearStoredAuth,
  getStoredAuthToken,
} from './authSession';

describe('authSession', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('reads the stored bearer token', () => {
    localStorage.setItem('token', 'test-token');

    expect(getStoredAuthToken()).toBe('test-token');
  });

  it('removes token and user from storage', () => {
    localStorage.setItem('token', 'test-token');
    localStorage.setItem('user', JSON.stringify({ id: 1 }));

    clearStoredAuth();

    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
  });

  it('clears storage and emits the session-cleared event with its reason', () => {
    localStorage.setItem('token', 'test-token');
    localStorage.setItem('user', JSON.stringify({ id: 1 }));
    const listener = vi.fn();
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    clearAuthSession('http-401');

    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
    expect(listener).toHaveBeenCalledOnce();
    expect(listener.mock.calls[0][0].detail).toEqual({ reason: 'http-401' });

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });
});
