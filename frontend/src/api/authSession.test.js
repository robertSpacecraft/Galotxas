import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AUTH_CHANNEL_NAME,
  AUTH_EVENT_SESSION_CHANGED,
  AUTH_EVENT_SESSION_ENDED,
  AUTH_SESSION_CLEARED_EVENT,
  INACTIVE_USER_AUTH_MESSAGE,
  broadcastAuthEvent,
  cleanupLegacyAuthStorage,
  clearAuthSession,
  getCsrfToken,
  isSafeMethod,
  isSessionExpected,
  resetSessionState,
  setCsrfToken,
  setSessionExpected,
  shouldInvalidateAuthSession,
  subscribeAuthEvents,
} from './authSession';

class FakeBroadcastChannel {
  static instances = [];

  constructor(name) {
    this.name = name;
    this.onmessage = null;
    this.closed = false;
    FakeBroadcastChannel.instances.push(this);
  }

  postMessage(data) {
    FakeBroadcastChannel.instances
      .filter((channel) => channel !== this && !channel.closed && channel.onmessage)
      .forEach((channel) => channel.onmessage({ data }));
  }

  close() {
    this.closed = true;
  }
}

describe('authSession in-memory state', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    resetSessionState();
  });

  it('keeps the CSRF token and the session expectation only in memory', () => {
    setCsrfToken('csrf-1');
    setSessionExpected(true);

    expect(getCsrfToken()).toBe('csrf-1');
    expect(isSessionExpected()).toBe(true);
    expect(localStorage).toHaveLength(0);
    expect(sessionStorage).toHaveLength(0);

    resetSessionState();

    expect(getCsrfToken()).toBeNull();
    expect(isSessionExpected()).toBe(false);
  });

  it('ignores unusable CSRF values', () => {
    setCsrfToken('csrf-1');
    setCsrfToken('');
    expect(getCsrfToken()).toBeNull();
    setCsrfToken(undefined);
    expect(getCsrfToken()).toBeNull();
  });

  it.each([['get', true], ['HEAD', true], ['options', true], ['post', false], ['PUT', false], ['patch', false], ['delete', false]])(
    'classifies %s safety',
    (method, safe) => {
      expect(isSafeMethod(method)).toBe(safe);
    },
  );

  it.each([401, 419])('identifies HTTP %s as an invalid session', (status) => {
    expect(shouldInvalidateAuthSession({ response: { status } })).toBe(true);
  });

  it('preserves ordinary forbidden responses and identifies the inactive-user 403', () => {
    expect(shouldInvalidateAuthSession({
      response: { status: 403, data: { message: 'No tienes permiso para realizar esta acción.' } },
    })).toBe(false);
    expect(shouldInvalidateAuthSession({
      response: { status: 403, data: { message: INACTIVE_USER_AUTH_MESSAGE } },
    })).toBe(true);
  });

  it('clears memory and emits the session-cleared event with its reason', () => {
    const listener = vi.fn();
    setCsrfToken('csrf-1');
    setSessionExpected(true);
    window.addEventListener(AUTH_SESSION_CLEARED_EVENT, listener);

    clearAuthSession('http-401');

    expect(getCsrfToken()).toBeNull();
    expect(isSessionExpected()).toBe(false);
    expect(listener).toHaveBeenCalledOnce();
    expect(listener.mock.calls[0][0].detail).toEqual({ reason: 'http-401' });

    window.removeEventListener(AUTH_SESSION_CLEARED_EVENT, listener);
  });
});

describe('legacy Bearer storage cleanup', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('removes the legacy token and profile before issuing the isolated best-effort logout', () => {
    const post = vi.spyOn(axios, 'post').mockImplementation(() => {
      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();

      return Promise.resolve({});
    });
    localStorage.setItem('token', 'legacy-token');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));

    cleanupLegacyAuthStorage();

    expect(localStorage).toHaveLength(0);
    expect(post).toHaveBeenCalledOnce();
    const [url, body, config] = post.mock.calls[0];
    expect(url).toMatch(/\/auth\/logout$/);
    expect(body).toBeNull();
    expect(config.headers.Authorization).toBe('Bearer legacy-token');
    expect(config.headers).not.toHaveProperty('X-Galotxas-Auth-Mode');
    expect(config.withCredentials).toBeUndefined();
  });

  it('does not restore the token or throw when the legacy logout fails', async () => {
    const post = vi.spyOn(axios, 'post').mockRejectedValue(new Error('offline'));
    localStorage.setItem('token', 'legacy-token');

    expect(() => cleanupLegacyAuthStorage()).not.toThrow();
    await Promise.resolve();

    expect(post).toHaveBeenCalledOnce();
    expect(localStorage).toHaveLength(0);
  });

  it('only removes a legacy profile when there is no token and sends nothing', () => {
    const post = vi.spyOn(axios, 'post');
    localStorage.setItem('user', JSON.stringify({ email: 'legacy@example.test' }));

    cleanupLegacyAuthStorage();

    expect(localStorage).toHaveLength(0);
    expect(post).not.toHaveBeenCalled();
  });
});

describe('cross-tab channel', () => {
  beforeEach(() => {
    FakeBroadcastChannel.instances = [];
    vi.stubGlobal('BroadcastChannel', FakeBroadcastChannel);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('delivers non-secret events to a listener and closes the channel on unsubscribe', () => {
    const handler = vi.fn();
    const unsubscribe = subscribeAuthEvents(handler);
    const [listener] = FakeBroadcastChannel.instances;
    expect(listener.name).toBe(AUTH_CHANNEL_NAME);

    const remote = new FakeBroadcastChannel(AUTH_CHANNEL_NAME);
    remote.postMessage({ type: AUTH_EVENT_SESSION_ENDED, source: 'other-tab' });
    remote.postMessage({ type: AUTH_EVENT_SESSION_CHANGED, source: 'other-tab' });
    remote.postMessage({ type: 'unknown', source: 'other-tab' });

    expect(handler.mock.calls).toEqual([[AUTH_EVENT_SESSION_ENDED], [AUTH_EVENT_SESSION_CHANGED]]);

    unsubscribe();

    expect(listener.closed).toBe(true);
    remote.postMessage({ type: AUTH_EVENT_SESSION_ENDED, source: 'other-tab' });
    expect(handler).toHaveBeenCalledTimes(2);
  });

  it('broadcasts only the event type and the tab id and ignores the own tab', () => {
    const handler = vi.fn();
    subscribeAuthEvents(handler);
    const remote = new FakeBroadcastChannel(AUTH_CHANNEL_NAME);
    const received = vi.fn();
    remote.onmessage = ({ data }) => received(data);

    broadcastAuthEvent(AUTH_EVENT_SESSION_CHANGED);

    expect(received).toHaveBeenCalledOnce();
    const message = received.mock.calls[0][0];
    expect(Object.keys(message).sort()).toEqual(['source', 'type']);
    expect(message.type).toBe(AUTH_EVENT_SESSION_CHANGED);

    // Un mensaje con el mismo origen (esta pestaña) no vuelve a la app.
    remote.postMessage({ type: AUTH_EVENT_SESSION_ENDED, source: message.source });
    expect(handler).not.toHaveBeenCalled();
  });

  it('degrades to a no-op without BroadcastChannel', () => {
    vi.unstubAllGlobals();
    vi.stubGlobal('BroadcastChannel', undefined);

    expect(() => broadcastAuthEvent(AUTH_EVENT_SESSION_ENDED)).not.toThrow();
    expect(subscribeAuthEvents(vi.fn())).toBeTypeOf('function');
  });
});
