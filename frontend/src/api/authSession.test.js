import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AUTH_SESSION_CLEARED_EVENT,
  AUTH_TOKEN_STORAGE_KEY,
  INACTIVE_USER_AUTH_MESSAGE,
  LEGACY_AUTH_USER_STORAGE_KEY,
  clearAuthSession,
  clearStoredAuth,
  discardLegacyStoredUser,
  getStoredAuthToken,
  shouldInvalidateAuthSession,
  storeAuthToken,
} from './authSession';

describe('authSession', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('reads the stored bearer token', () => {
    storeAuthToken('test-token');

    expect(getStoredAuthToken()).toBe('test-token');
    expect(localStorage.getItem(LEGACY_AUTH_USER_STORAGE_KEY)).toBeNull();
  });

  it('stores only the bearer token and removes it for an empty value', () => {
    storeAuthToken('test-token');

    expect(localStorage).toHaveLength(1);
    expect(localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)).toBe('test-token');

    storeAuthToken(null);

    expect(localStorage).toHaveLength(0);
  });

  it('discards a legacy user without migrating its data', () => {
    localStorage.setItem(LEGACY_AUTH_USER_STORAGE_KEY, JSON.stringify({ email: 'legacy@example.test' }));

    discardLegacyStoredUser();

    expect(localStorage.getItem(LEGACY_AUTH_USER_STORAGE_KEY)).toBeNull();
  });

  it.each([401, 419])('identifies HTTP %s as an invalid session', (status) => {
    expect(shouldInvalidateAuthSession({ response: { status } })).toBe(true);
  });

  it('preserves ordinary forbidden responses and identifies the revoked inactive-user token', () => {
    expect(shouldInvalidateAuthSession({
      response: { status: 403, data: { message: 'No tienes permiso para realizar esta acción.' } },
    })).toBe(false);
    expect(shouldInvalidateAuthSession({
      response: { status: 403, data: { message: INACTIVE_USER_AUTH_MESSAGE } },
    })).toBe(true);
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
