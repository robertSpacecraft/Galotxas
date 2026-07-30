import { describe, expect, it } from 'vitest';
import {
  getActivePublicNavigationItem,
  getPublicNavigationAriaCurrent,
  publicNavigation,
} from './publicNavigation';

describe('publicNavigation', () => {
  it('contains only the functional first-level destinations in their intended order', () => {
    expect(publicNavigation.map(({ id, label, to }) => ({ id, label, to }))).toEqual([
      { id: 'home', label: 'Inicio', to: '/' },
      { id: 'competition', label: 'Competición', to: '/competicion' },
      { id: 'learn', label: 'Aprende a jugar', to: '/aprende-a-jugar' },
      { id: 'school', label: 'Escuela de Galotxas', to: '/escuela' },
    ]);

    const serializedNavigation = JSON.stringify(publicNavigation);

    for (const excludedValue of [
      'Torneos',
      'Rankings',
      'Club',
      '/contenidos',
    ]) {
      expect(serializedNavigation).not.toContain(excludedValue);
    }
  });

  it.each([
    ['/', 'home'],
    ['/competicion', 'competition'],
    ['/torneos', 'competition'],
    ['/torneos/campeonato-1', 'competition'],
    ['/categories/7', 'competition'],
    ['/categories/7/standings', 'competition'],
    ['/categories/7/schedule', 'competition'],
    ['/matches/15', 'competition'],
    ['/rankings', 'competition'],
    ['/aprende-a-jugar', 'learn'],
    ['/aprende-a-jugar/manual', 'learn'],
    ['/aprende-a-jugar/manual/reglamento/el-saque', 'learn'],
    ['/aprende-a-jugar/manual/conceptos/juego/saque', 'learn'],
    ['/escuela', 'school'],
    ['/escuela/alumno', 'school'],
    ['/contenidos/nosotros', null],
    ['/nosotros', null],
    ['/torneos/1/otra-ruta', null],
  ])('matches %s to the expected first-level item', (pathname, expectedId) => {
    expect(getActivePublicNavigationItem(pathname)?.id ?? null).toBe(expectedId);
  });

  it('distinguishes the current page from a current competition location', () => {
    const competition = publicNavigation[1];

    expect(getPublicNavigationAriaCurrent(competition, '/competicion')).toBe('page');
    expect(getPublicNavigationAriaCurrent(competition, '/torneos')).toBe('location');
    expect(getPublicNavigationAriaCurrent(competition, '/contenidos/nosotros')).toBeUndefined();
  });

  it('distinguishes the Aprende landing from all of its descendants', () => {
    const learn = publicNavigation[2];

    expect(getPublicNavigationAriaCurrent(learn, '/aprende-a-jugar')).toBe('page');
    expect(getPublicNavigationAriaCurrent(learn, '/aprende-a-jugar/manual')).toBe('location');
    expect(getPublicNavigationAriaCurrent(learn, '/competicion')).toBeUndefined();
  });

  it('distinguishes the School landing from a future descendant', () => {
    const school = publicNavigation[3];

    expect(getPublicNavigationAriaCurrent(school, '/escuela')).toBe('page');
    expect(getPublicNavigationAriaCurrent(school, '/escuela/alumno')).toBe('location');
    expect(getPublicNavigationAriaCurrent(school, '/academy')).toBeUndefined();
  });
});
