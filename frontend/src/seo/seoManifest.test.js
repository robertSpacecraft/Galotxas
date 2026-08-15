import { describe, expect, it } from 'vitest';
import {
  getCanonicalSitemapRoutes,
  publicSeoAliases,
  resolveSeoRoute,
  seoRouteClassifications,
} from './seoManifest';

describe('public SEO route manifest', () => {
  it.each([
    '/',
    '/competicion',
    '/aprende-a-jugar',
    '/aprende-a-jugar/manual',
    '/aprende-a-jugar/manual/reglamento/saque',
    '/aprende-a-jugar/manual/conceptos/juego/bote',
    '/escuela',
    '/club/quienes-somos',
    '/club/contacto',
    '/club/federarse',
    '/club/documentos',
    '/legal/aviso-legal',
    '/legal/privacidad',
    '/legal/cookies',
  ])('classifies %s as an indexable canonical route', (pathname) => {
    const route = resolveSeoRoute(pathname);

    expect(route.classification).toBe(seoRouteClassifications.indexableCanonical);
    expect(route.canonicalPath).toBe(pathname);
  });

  it.each([
    ['/nosotros', '/club/quienes-somos'],
    ['/contenidos/nosotros', '/club/quienes-somos'],
    ['/contenidos/contacto', '/club/contacto'],
    ['/contenidos/federarse', '/club/federarse'],
    ['/contenidos/documentos', '/club/documentos'],
  ])('keeps %s as an alias of %s outside the sitemap', (pathname, canonicalPath) => {
    const route = resolveSeoRoute(pathname);

    expect(route.classification).toBe(seoRouteClassifications.indexableAlias);
    expect(route.canonicalPath).toBe(canonicalPath);
    expect(route.sitemap).toBe(false);
  });

  it('normalizes trailing slash, casing, query and fragment for canonical resolution', () => {
    expect(resolveSeoRoute('/COMPETICION/?vista=lista#actual')).toMatchObject({
      id: 'competition',
      canonicalPath: '/competicion',
    });
  });

  it.each([
    '/torneos',
    '/torneos/10',
    '/categories/20',
    '/categories/20/standings',
    '/categories/20/schedule',
    '/matches/30',
    '/rankings',
    '/contenidos',
    '/contenidos/academy',
  ])('keeps the public volatile or legacy route %s out of indexing', (pathname) => {
    expect(resolveSeoRoute(pathname)).toMatchObject({
      classification: seoRouteClassifications.noindexPublic,
      canonicalPath: null,
      sitemap: false,
    });
  });

  it.each(['/login', '/register', '/forgot-password', '/reset-password', '/player'])(
    'classifies %s as private noindex without canonical', (pathname) => {
      expect(resolveSeoRoute(pathname)).toMatchObject({
        classification: seoRouteClassifications.noindexPrivate,
        canonicalPath: null,
      });
    },
  );

  it('isolates token and unknown routes from canonical indexing', () => {
    expect(resolveSeoRoute('/public-identity/confirm').classification)
      .toBe(seoRouteClassifications.tokenOrTransient);
    expect(resolveSeoRoute('/club').classification).toBe(seoRouteClassifications.notFound);
    expect(resolveSeoRoute('/aprende').classification).toBe(seoRouteClassifications.notFound);
    expect(resolveSeoRoute('/ruta-desconocida').canonicalPath).toBeNull();
  });

  it('keeps canonical sitemap declarations unique and excludes aliases', () => {
    const sitemapRoutes = getCanonicalSitemapRoutes();
    const paths = sitemapRoutes.map((route) => route.path);

    expect(new Set(paths).size).toBe(paths.length);
    expect(paths).toContain('/club/quienes-somos');
    expect(paths).toContain('/legal/privacidad');
    expect(paths).not.toContain('/nosotros');
    expect(publicSeoAliases).toHaveLength(5);
  });

  it('keeps Rankings noindex while describing all four public scopes', () => {
    expect(resolveSeoRoute('/rankings')).toMatchObject({
      classification: seoRouteClassifications.noindexPublic,
      canonicalPath: null,
      sitemap: false,
      description: 'Consulta rankings públicos por histórico, temporada, campeonato y categoría con identidad minimizada.',
    });
  });
});
