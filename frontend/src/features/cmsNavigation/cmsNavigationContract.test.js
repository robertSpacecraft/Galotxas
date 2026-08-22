import { describe, expect, it } from 'vitest';
import {
  InvalidCmsNavigationResponseError,
  normalizeCmsNavigationResponse,
} from './cmsNavigationContract';

const item = (overrides = {}) => ({
  slot: 'club',
  label: 'Historia',
  url: '/contenidos/historia',
  sort_order: 10,
  ...overrides,
});

describe('cmsNavigationContract', () => {
  it('accepts the closed response and normalizes labels and stable order', () => {
    expect(normalizeCmsNavigationResponse({
      message: null,
      data: [
        item({ label: ' Última ', url: '/contenidos/ultima', sort_order: 20 }),
        item({ label: 'Primera A', url: '/contenidos/primera-a' }),
        item({ label: 'Primera B', url: '/contenidos/primera-b' }),
      ],
    })).toEqual([
      item({ label: 'Primera A', url: '/contenidos/primera-a' }),
      item({ label: 'Primera B', url: '/contenidos/primera-b' }),
      item({ label: 'Última', url: '/contenidos/ultima', sort_order: 20 }),
    ]);
  });

  it.each([
    ['unexpected slot', { slot: 'footer' }],
    ['external URL', { url: 'https://example.test' }],
    ['protocol relative URL', { url: '//example.test/historia' }],
    ['script URL', { url: 'javascript:alert(1)' }],
    ['product route', { url: '/login' }],
    ['malformed slug', { url: '/contenidos/Historia/' }],
    ['reserved facade', { url: '/contenidos/contacto' }],
    ['empty label', { label: '   ' }],
    ['HTML label', { label: '<strong>Historia</strong>' }],
    ['URL label', { label: 'https://example.test' }],
    ['control label', { label: 'Historia\nclub' }],
    ['negative order', { sort_order: -1 }],
    ['fractional order', { sort_order: 1.5 }],
    ['extra field', { id: 4 }],
  ])('omits %s without breaking structural navigation', (_case, override) => {
    expect(normalizeCmsNavigationResponse({ data: [item(override)] })).toEqual([]);
  });

  it('keeps the first duplicate URL and omits subsequent placements', () => {
    expect(normalizeCmsNavigationResponse({
      data: [item({ label: 'Primera' }), item({ label: 'Duplicada', sort_order: 20 })],
    })).toEqual([item({ label: 'Primera' })]);
  });

  it.each([
    null,
    [],
    {},
    { data: null },
    { message: 'unexpected', data: [] },
    { message: null, data: [], meta: {} },
  ])('rejects malformed response roots', (payload) => {
    expect(() => normalizeCmsNavigationResponse(payload))
      .toThrow(InvalidCmsNavigationResponseError);
  });
});
