import { describe, expect, it } from 'vitest';
import { InvalidSponsorsResponseError, normalizeSponsors } from './sponsorContract';

const sponsor = (overrides = {}) => ({
  id: 7,
  name: 'Colaborador Uno',
  logo: {
    url: 'https://api.example.test/api/v1/sponsors/7/logo',
    width: 600,
    height: 300,
  },
  website_url: 'https://example.com',
  ...overrides,
});

describe('sponsorContract', () => {
  it('normalizes the closed public contract while preserving API order', () => {
    const result = normalizeSponsors([
      sponsor({ id: 2, name: '  Segundo  ' }),
      sponsor({ id: 1, name: 'Primero', website_url: null }),
    ]);

    expect(result.map(({ id }) => id)).toEqual([2, 1]);
    expect(result[0].name).toBe('Segundo');
    expect(result[1].website_url).toBeNull();
  });

  it.each([
    null,
    {},
    [sponsor({ id: 0 })],
    [sponsor({ name: ' ' })],
    [sponsor({ logo: { url: '/relative', width: 1, height: 1 } })],
    [sponsor({ logo: { url: 'https://api.test/logo', width: 0, height: 1 } })],
    [sponsor({ website_url: 'http://example.com' })],
    [sponsor({ website_url: 'javascript:alert(1)' })],
  ])('rejects a malformed response without returning partial content', (payload) => {
    expect(() => normalizeSponsors(payload)).toThrow(InvalidSponsorsResponseError);
  });
});
