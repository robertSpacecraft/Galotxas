import { describe, expect, it } from 'vitest';
import { getLegalPage, legalPages, legalPath } from './legalRoutes';

describe('legalRoutes', () => {
  it('exposes only the three approved exact destinations', () => {
    expect(Object.values(legalPages)).toEqual([
      { id: 'LEG-001', label: 'Aviso legal', path: '/legal/aviso-legal' },
      { id: 'LEG-002', label: 'Privacidad', path: '/legal/privacidad' },
      { id: 'LEG-003', label: 'Cookies', path: '/legal/cookies' },
    ]);
    expect(Object.values(legalPages).some(({ path }) => path === '/legal')).toBe(false);
  });

  it('fails closed for unknown document ids', () => {
    expect(getLegalPage('LEG-999')).toBeNull();
    expect(legalPath('LEG-999')).toBeNull();
  });
});
