import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AUTH_SESSION_CLEARED_EVENT,
  INACTIVE_USER_AUTH_MESSAGE,
  getCsrfToken,
  isSessionExpected,
  resetSessionState,
  setCsrfToken,
  setSessionExpected,
} from './authSession';
import api, { refreshSpaCsrfToken } from './client';

const response = (config, data = { data: null }) => ({
  config,
  data,
  headers: {},
  status: 200,
  statusText: 'OK',
});

const rejected = (config, status, message = null) => Promise.reject({
  config,
  response: { status, data: message ? { message } : null },
});

const csrfBody = (token) => ({ data: { csrf_token: token } });

describe('SPA session API client', () => {
  const originalAdapter = api.defaults.adapter;
  let listener;

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    resetSessionState();
    listener = vi.fn();
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });

  afterEach(() => {
    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
    api.defaults.adapter = originalAdapter;
  });

  describe('request shape', () => {
    it('always sends credentials and never an Authorization header', async () => {
      localStorage.setItem('token', 'legacy-token');
      setSessionExpected(true);
      const adapter = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = adapter;

      await api.get('/anything');

      const config = adapter.mock.calls[0][0];
      expect(config.withCredentials).toBe(true);
      expect(config.headers.get('Authorization')).toBeFalsy();
    });

    it('does not opt into session mode while no session is expected', async () => {
      setCsrfToken('csrf-1');
      const adapter = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = adapter;

      await api.post('/contact-requests', {});

      const headers = adapter.mock.calls[0][0].headers;
      expect(headers.get('X-Galotxas-Auth-Mode')).toBeFalsy();
      expect(headers.get('X-CSRF-TOKEN')).toBeFalsy();
    });

    it('adds the mode header when the session is expected or the request forces it', async () => {
      const adapter = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = adapter;

      await api.get('/me', { sessionMode: true });
      setSessionExpected(true);
      await api.get('/seasons');

      expect(adapter.mock.calls[0][0].headers.get('X-Galotxas-Auth-Mode')).toBe('session');
      expect(adapter.mock.calls[1][0].headers.get('X-Galotxas-Auth-Mode')).toBe('session');
    });

    it.each(['post', 'put', 'patch', 'delete'])('sends the CSRF header on %s only in session mode', async (method) => {
      setSessionExpected(true);
      setCsrfToken('csrf-1');
      const adapter = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = adapter;

      await api[method]('/mutate', ...(method === 'delete' ? [] : [{ a: 1 }]));

      expect(adapter.mock.calls[0][0].headers.get('X-CSRF-TOKEN')).toBe('csrf-1');
    });

    it.each(['get', 'head', 'options'])('does not send the CSRF header on %s nor in the query string', async (method) => {
      setSessionExpected(true);
      setCsrfToken('csrf-1');
      const adapter = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = adapter;

      await api[method]('/read');

      const config = adapter.mock.calls[0][0];
      expect(config.headers.get('X-CSRF-TOKEN')).toBeFalsy();
      expect(config.url).not.toContain('csrf-1');
      expect(config.params).toBeUndefined();
    });
  });

  describe('CSRF refresh', () => {
    it('stores the token in memory only, forcing session mode', async () => {
      const adapter = vi.fn((config) => Promise.resolve(response(config, csrfBody('fresh'))));
      api.defaults.adapter = adapter;

      await expect(refreshSpaCsrfToken()).resolves.toBe('fresh');

      const config = adapter.mock.calls[0][0];
      expect(config.url).toBe('/auth/csrf');
      expect(config.headers.get('X-Galotxas-Auth-Mode')).toBe('session');
      expect(getCsrfToken()).toBe('fresh');
      expect(localStorage).toHaveLength(0);
      expect(sessionStorage).toHaveLength(0);
    });

    it('fails closed without a usable token and releases the shared promise', async () => {
      api.defaults.adapter = vi.fn((config) => Promise.resolve(response(config, { data: {} })));

      await expect(refreshSpaCsrfToken()).rejects.toThrow();
      expect(getCsrfToken()).toBeNull();

      api.defaults.adapter = vi.fn((config) => Promise.resolve(response(config, csrfBody('again'))));
      await expect(refreshSpaCsrfToken()).resolves.toBe('again');
    });

    it('shares one request between simultaneous refreshes', async () => {
      const adapter = vi.fn((config) => Promise.resolve(response(config, csrfBody('shared'))));
      api.defaults.adapter = adapter;

      await Promise.all([refreshSpaCsrfToken(), refreshSpaCsrfToken(), refreshSpaCsrfToken()]);

      expect(adapter).toHaveBeenCalledOnce();
    });
  });

  describe('HTTP 419', () => {
    it('refreshes the CSRF token once and retries the original request with the same body', async () => {
      setSessionExpected(true);
      setCsrfToken('stale');
      const seen = [];
      api.defaults.adapter = vi.fn((config) => {
        if (config.url === '/auth/csrf') {
          return Promise.resolve(response(config, csrfBody('fresh')));
        }
        seen.push({ csrf: config.headers.get('X-CSRF-TOKEN'), data: config.data });

        return seen.length === 1 ? rejected(config, 419) : Promise.resolve(response(config));
      });

      await api.post('/me/player-profile', { nickname: 'Alias' });

      expect(seen).toHaveLength(2);
      expect(seen[0].csrf).toBe('stale');
      expect(seen[1].csrf).toBe('fresh');
      expect(JSON.parse(seen[1].data)).toEqual({ nickname: 'Alias' });
      expect(listener).not.toHaveBeenCalled();
      expect(isSessionExpected()).toBe(true);
    });

    it('does not loop: a second 419 fails the request and invalidates the session', async () => {
      setSessionExpected(true);
      setCsrfToken('stale');
      const adapter = vi.fn((config) => (config.url === '/auth/csrf'
        ? Promise.resolve(response(config, csrfBody('fresh')))
        : rejected(config, 419)));
      api.defaults.adapter = adapter;

      await expect(api.post('/mutate', {})).rejects.toMatchObject({ response: { status: 419 } });

      expect(adapter.mock.calls.filter(([config]) => config.url === '/mutate')).toHaveLength(2);
      expect(adapter.mock.calls.filter(([config]) => config.url === '/auth/csrf')).toHaveLength(1);
      expect(listener).toHaveBeenCalledOnce();
      expect(listener.mock.calls[0][0].detail).toEqual({ reason: 'http-419' });
      expect(isSessionExpected()).toBe(false);
      expect(getCsrfToken()).toBeNull();
    });

    it('never retries the CSRF endpoint itself', async () => {
      setSessionExpected(true);
      const adapter = vi.fn((config) => rejected(config, 419));
      api.defaults.adapter = adapter;

      await expect(refreshSpaCsrfToken()).rejects.toMatchObject({ response: { status: 419 } });

      expect(adapter).toHaveBeenCalledOnce();
    });

    it('shares one CSRF refresh between concurrent 419 responses', async () => {
      setSessionExpected(true);
      setCsrfToken('stale');
      const attempts = {};
      const adapter = vi.fn((config) => {
        if (config.url === '/auth/csrf') {
          return Promise.resolve(response(config, csrfBody('fresh')));
        }
        attempts[config.url] = (attempts[config.url] ?? 0) + 1;

        return attempts[config.url] === 1 ? rejected(config, 419) : Promise.resolve(response(config));
      });
      api.defaults.adapter = adapter;

      await Promise.all([api.post('/a', {}), api.post('/b', {}), api.delete('/c')]);

      expect(adapter.mock.calls.filter(([config]) => config.url === '/auth/csrf')).toHaveLength(1);
      expect(attempts).toEqual({ '/a': 2, '/b': 2, '/c': 2 });
    });

    it('does not treat a 419 outside session mode as a CSRF failure', async () => {
      const adapter = vi.fn((config) => rejected(config, 419));
      api.defaults.adapter = adapter;

      await expect(api.post('/contact-requests', {})).rejects.toMatchObject({ response: { status: 419 } });

      expect(adapter).toHaveBeenCalledOnce();
      expect(listener).not.toHaveBeenCalled();
    });

    it('rejects with the original 419 without invalidating when the refresh cannot be completed', async () => {
      setSessionExpected(true);
      api.defaults.adapter = vi.fn((config) => (config.url === '/auth/csrf'
        ? Promise.reject(new Error('Network Error'))
        : rejected(config, 419)));

      await expect(api.post('/mutate', {})).rejects.toMatchObject({ response: { status: 419 } });

      expect(listener).not.toHaveBeenCalled();
      expect(isSessionExpected()).toBe(true);
    });
  });

  describe('401 / 403 invalidation', () => {
    it('invalidates an expected session after HTTP 401', async () => {
      setSessionExpected(true);
      setCsrfToken('csrf-1');
      api.defaults.adapter = vi.fn((config) => rejected(config, 401));

      await expect(api.get('/protected')).rejects.toMatchObject({ response: { status: 401 } });

      expect(listener).toHaveBeenCalledOnce();
      expect(listener.mock.calls[0][0].detail).toEqual({ reason: 'http-401' });
      expect(isSessionExpected()).toBe(false);
      expect(getCsrfToken()).toBeNull();
    });

    it('does not announce an invalidation for anonymous 401 responses', async () => {
      api.defaults.adapter = vi.fn((config) => rejected(config, 401));

      await expect(api.get('/me', { sessionMode: true })).rejects.toMatchObject({ response: { status: 401 } });

      expect(listener).not.toHaveBeenCalled();
    });

    it('invalidates after the inactive-user 403', async () => {
      setSessionExpected(true);
      api.defaults.adapter = vi.fn((config) => rejected(config, 403, INACTIVE_USER_AUTH_MESSAGE));

      await expect(api.get('/me')).rejects.toMatchObject({ response: { status: 403 } });

      expect(listener).toHaveBeenCalledOnce();
      expect(isSessionExpected()).toBe(false);
    });

    it('preserves the session and the CSRF token after an ordinary 403', async () => {
      setSessionExpected(true);
      setCsrfToken('csrf-1');
      api.defaults.adapter = vi.fn((config) => rejected(config, 403, 'No tienes permiso para realizar esta acción.'));

      await expect(api.get('/forbidden')).rejects.toMatchObject({ response: { status: 403 } });

      expect(listener).not.toHaveBeenCalled();
      expect(isSessionExpected()).toBe(true);
      expect(getCsrfToken()).toBe('csrf-1');

      const next = vi.fn((config) => Promise.resolve(response(config)));
      api.defaults.adapter = next;
      await api.post('/still-authenticated', {});

      expect(next.mock.calls[0][0].headers.get('X-Galotxas-Auth-Mode')).toBe('session');
      expect(next.mock.calls[0][0].headers.get('X-CSRF-TOKEN')).toBe('csrf-1');
    });
  });
});
