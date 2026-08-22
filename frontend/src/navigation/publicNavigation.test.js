import { describe, expect, it } from 'vitest';
import {
  composePublicNavigation,
  getActivePublicNavigationItem,
  getPublicNavigationAriaCurrent,
  getPublicNavigationChild,
  getPublicNavigationItem,
  getVisiblePublicNavigation,
  matchesNavigationItem,
  navigationAudiences,
  navigationItemTypes,
  publicFooterNavigation,
  publicLegalNavigation,
  publicNavigation,
  publicSiteIdentity,
  publicSocialLinks,
} from './publicNavigation';

describe('publicNavigation', () => {
  it('models the approved order, item types, visibility and audience', () => {
    expect(publicNavigation.map(({ id, label, type, visible, audience }) => ({
      id,
      label,
      type,
      visible,
      audience,
    }))).toEqual([
      {
        id: 'home',
        label: 'Inicio',
        type: navigationItemTypes.link,
        visible: true,
        audience: navigationAudiences.public,
      },
      {
        id: 'competition',
        label: 'Competición',
        type: navigationItemTypes.disclosure,
        visible: true,
        audience: navigationAudiences.public,
      },
      {
        id: 'news',
        label: 'Noticias',
        type: navigationItemTypes.link,
        visible: true,
        audience: navigationAudiences.public,
      },
      {
        id: 'learn',
        label: 'Aprende',
        type: navigationItemTypes.disclosure,
        visible: true,
        audience: navigationAudiences.public,
      },
      {
        id: 'club',
        label: 'Club',
        type: navigationItemTypes.disclosure,
        visible: true,
        audience: navigationAudiences.public,
      },
    ]);

    expect(getVisiblePublicNavigation()).toEqual(publicNavigation);
  });

  it('keeps disclosure parents without routes and declares their canonical children', () => {
    const competition = getPublicNavigationItem('competition');
    const learn = getPublicNavigationItem('learn');
    const club = getPublicNavigationItem('club');

    expect(competition).not.toHaveProperty('to');
    expect(learn).not.toHaveProperty('to');
    expect(club).not.toHaveProperty('to');
    expect(competition.children.map(({ label, to }) => ({ label, to }))).toEqual([
      { label: 'Vista general', to: '/competicion' },
      { label: 'Campeonatos', to: '/torneos' },
      { label: 'Rankings', to: '/rankings' },
    ]);
    expect(learn.children.map(({ label, to }) => ({ label, to }))).toEqual([
      { label: 'Aprende a jugar', to: '/aprende-a-jugar' },
      { label: 'Manual y reglas', to: '/aprende-a-jugar/manual' },
      { label: 'Escuela de Galotxas', to: '/escuela' },
    ]);
    expect(club.children.map(({ label, to }) => ({ label, to }))).toEqual([
      { label: 'Quiénes somos', to: '/club/quienes-somos' },
      { label: 'Contacto', to: '/club/contacto' },
      { label: 'Federarse', to: '/club/federarse' },
      { label: 'Documentos', to: '/club/documentos' },
    ]);
    expect(JSON.stringify(publicNavigation)).not.toContain('"to":"/club"');
    expect(JSON.stringify(publicNavigation)).not.toContain('"to":"/aprende"');
  });

  it.each([
    ['/', 'home'],
    ['/competicion', 'competition'],
    ['/competicion/temporada', null],
    ['/torneos', 'competition'],
    ['/torneos/campeonato-1', 'competition'],
    ['/categories/7/standings', 'competition'],
    ['/matches/15', 'competition'],
    ['/rankings', 'competition'],
    ['/noticias', 'news'],
    ['/noticias/cronica-final', 'news'],
    ['/aprende-a-jugar', 'learn'],
    ['/aprende-a-jugar/manual', 'learn'],
    ['/aprende-a-jugar/manual/reglamento/el-saque', 'learn'],
    ['/escuela', 'learn'],
    ['/escuela/alumno', 'learn'],
    ['/club/quienes-somos', 'club'],
    ['/club/contacto', 'club'],
    ['/club/desconocido', 'club'],
    ['/contenidos/nosotros', null],
    ['/login', null],
    ['/player', null],
    ['/nosotros', null],
  ])('matches %s to the expected first-level item', (pathname, expectedId) => {
    expect(getActivePublicNavigationItem(pathname)?.id ?? null).toBe(expectedId);
  });

  it('uses exact and prefix matchers while reserving aria-current page for exact destinations', () => {
    const competition = getPublicNavigationItem('competition');
    const competitionOverview = getPublicNavigationChild('competition', 'competition-overview');
    const championships = getPublicNavigationChild('competition', 'competition-championships');
    const manual = getPublicNavigationChild('learn', 'manual');

    expect(matchesNavigationItem(competition, '/torneos/12')).toBe(true);
    expect(matchesNavigationItem(championships, '/torneos/12')).toBe(false);
    expect(matchesNavigationItem(manual, '/aprende-a-jugar/manual/reglamento/saque')).toBe(true);
    expect(getPublicNavigationAriaCurrent(competitionOverview, '/competicion')).toBe('page');
    expect(getPublicNavigationAriaCurrent(championships, '/torneos')).toBe('page');
    expect(getPublicNavigationAriaCurrent(championships, '/torneos/12')).toBeUndefined();
    expect(getPublicNavigationAriaCurrent(manual, '/aprende-a-jugar/manual')).toBe('page');
    expect(getPublicNavigationAriaCurrent(
      manual,
      '/aprende-a-jugar/manual/reglamento/saque',
    )).toBeUndefined();
  });

  it('centralizes the footer identity, canonical Club links and confirmed social URLs', () => {
    expect(publicSiteIdentity.name).toBe('Club Galotxes Monòver');
    expect(publicFooterNavigation.map((item) => item.to)).toEqual([
      '/club/quienes-somos',
      '/club/contacto',
      '/club/federarse',
      '/club/documentos',
    ]);
    expect(publicSocialLinks).toEqual([
      expect.objectContaining({
        label: 'Facebook',
        href: 'https://www.facebook.com/galotxes.monover?locale=es_ES',
      }),
      expect.objectContaining({
        label: 'Instagram',
        href: 'https://www.instagram.com/clubgalotxes/',
      }),
    ]);
    expect(publicLegalNavigation).toEqual([
      { id: 'LEG-001', label: 'Aviso legal', to: '/legal/aviso-legal' },
      { id: 'LEG-002', label: 'Privacidad', to: '/legal/privacidad' },
      { id: 'LEG-003', label: 'Cookies', to: '/legal/cookies' },
    ]);
  });

  it('appends sorted CMS links to Club without mutating or changing other branches', () => {
    const originalSnapshot = JSON.stringify(publicNavigation);
    const composed = composePublicNavigation(publicNavigation, [
      { slot: 'club', label: 'Memoria', url: '/contenidos/memoria', sort_order: 20 },
      { slot: 'club', label: 'Historia', url: '/contenidos/historia', sort_order: 10 },
    ]);
    const club = getPublicNavigationItem('club', composed);

    expect(composed).not.toBe(publicNavigation);
    expect(composed.slice(0, 4)).toEqual(publicNavigation.slice(0, 4));
    expect(club.children.slice(0, 4)).toEqual(getPublicNavigationItem('club').children);
    expect(club.children.slice(4).map(({ id, label, to }) => ({ id, label, to }))).toEqual([
      {
        id: 'cms-navigation:/contenidos/historia',
        label: 'Historia',
        to: '/contenidos/historia',
      },
      {
        id: 'cms-navigation:/contenidos/memoria',
        label: 'Memoria',
        to: '/contenidos/memoria',
      },
    ]);
    expect(getActivePublicNavigationItem('/contenidos/historia', composed)?.id).toBe('club');
    expect(getPublicNavigationAriaCurrent(club.children[4], '/contenidos/historia/')).toBe('page');
    expect(getActivePublicNavigationItem('/contenidos/no-asignada', composed)).toBeNull();
    expect(JSON.stringify(publicNavigation)).toBe(originalSnapshot);
    expect(Object.isFrozen(composed)).toBe(true);
    expect(Object.isFrozen(club.children)).toBe(true);
  });

  it('omits invalid and duplicate CMS links while preserving the structural tree identity', () => {
    const invalid = composePublicNavigation(publicNavigation, [
      { slot: 'club', label: 'Contacto', url: '/contenidos/contacto', sort_order: 0 },
      { slot: 'club', label: 'Externa', url: 'https://example.test', sort_order: 0 },
    ]);

    expect(invalid).toBe(publicNavigation);
  });
});
