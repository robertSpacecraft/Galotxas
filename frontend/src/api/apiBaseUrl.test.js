import { describe, expect, it } from 'vitest';
import { resolveApiBaseUrl } from './apiBaseUrl';

describe('resolveApiBaseUrl', () => {
  it('trims and prioritizes the configured URL', () => {
    expect(resolveApiBaseUrl({
      configuredUrl: '  https://api.galotxas.test/api/v1  ',
      isDevelopment: true,
    })).toBe('https://api.galotxas.test/api/v1');
  });

  it('uses the local API fallback during development', () => {
    expect(resolveApiBaseUrl({ configuredUrl: '', isDevelopment: true }))
      .toBe('http://localhost:8080/api/v1');
  });

  it('uses the relative API fallback in production', () => {
    expect(resolveApiBaseUrl({ configuredUrl: undefined, isDevelopment: false }))
      .toBe('/api/v1');
  });

  it('treats a whitespace-only configured URL as missing', () => {
    expect(resolveApiBaseUrl({ configuredUrl: '   ', isDevelopment: false }))
      .toBe('/api/v1');
  });
});
