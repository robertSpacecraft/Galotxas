import { describe, expect, it } from 'vitest';
import {
  clubPageIds,
  clubPages,
  clubPath,
  getClubPage,
} from './clubRoutes';

describe('clubRoutes', () => {
  it('keeps the canonical route-to-slug contract closed and explicit', () => {
    expect(clubPageIds).toEqual(['about', 'contact', 'membership', 'documents']);
    expect(Object.values(clubPages).map(({ path, slug }) => [path, slug])).toEqual([
      ['/club/quienes-somos', 'nosotros'],
      ['/club/contacto', 'contacto'],
      ['/club/federarse', 'federarse'],
      ['/club/documentos', 'documentos'],
    ]);
  });

  it('does not provide a generic Club path or accept arbitrary identifiers', () => {
    expect(clubPath('about')).toBe('/club/quienes-somos');
    expect(clubPath('unknown')).toBeNull();
    expect(getClubPage('nosotros')).toBeNull();
    expect(Object.isFrozen(clubPages)).toBe(true);
    expect(Object.values(clubPages).every(Object.isFrozen)).toBe(true);
  });
});
