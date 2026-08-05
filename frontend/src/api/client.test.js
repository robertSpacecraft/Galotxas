import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AUTH_SESSION_CLEARED_EVENT,
  INACTIVE_USER_AUTH_MESSAGE,
} from './authSession';
import api from './client';

const rejectedResponse = (status, message = null) => vi.fn((config) => Promise.reject({
  config,
  response: {
    status,
    data: message ? { message } : null,
  },
}));

const successfulResponse = () => vi.fn((config) => Promise.resolve({
  config,
  data: { data: null },
  headers: {},
  status: 200,
  statusText: 'OK',
}));

describe('authenticated API client', () => {
  const originalAdapter = api.defaults.adapter;

  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    api.defaults.adapter = originalAdapter;
  });

  it.each([401, 419])('clears an invalid session after HTTP %s', async (status) => {
    const listener = vi.fn();
    localStorage.setItem('token', 'invalid-token');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));
    api.defaults.adapter = rejectedResponse(status);
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    await expect(api.get('/protected')).rejects.toMatchObject({ response: { status } });

    expect(localStorage).toHaveLength(0);
    expect(listener).toHaveBeenCalledOnce();
    expect(listener.mock.calls[0][0].detail).toEqual({ reason: `http-${status}` });

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });

  it('preserves the session after an ordinary 403 and sends its token on the next request', async () => {
    const listener = vi.fn();
    localStorage.setItem('token', 'persistent-token');
    api.defaults.adapter = rejectedResponse(403, 'No tienes permiso para realizar esta acción.');
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    await expect(api.get('/forbidden')).rejects.toMatchObject({ response: { status: 403 } });

    expect(localStorage.getItem('token')).toBe('persistent-token');
    expect(listener).not.toHaveBeenCalled();

    const nextAdapter = successfulResponse();
    api.defaults.adapter = nextAdapter;
    await api.get('/still-authenticated');

    expect(nextAdapter).toHaveBeenCalledOnce();
    expect(nextAdapter.mock.calls[0][0].headers.get('Authorization'))
      .toBe('Bearer persistent-token');

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });

  it('clears the explicitly revoked token when an inactive user receives 403', async () => {
    localStorage.setItem('token', 'revoked-token');
    api.defaults.adapter = rejectedResponse(403, INACTIVE_USER_AUTH_MESSAGE);

    await expect(api.get('/me')).rejects.toMatchObject({ response: { status: 403 } });

    expect(localStorage.getItem('token')).toBeNull();
  });
});
