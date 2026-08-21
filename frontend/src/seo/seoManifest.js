import { clubPages } from '../features/club/clubRoutes.js';
import { legalPages } from '../features/legal/legalRoutes.js';
import { schoolPath } from '../features/school/schoolRoutes.js';

export const PUBLIC_SITE_NAME = 'Club Galotxes Monòver';

export const seoRouteClassifications = Object.freeze({
  indexableCanonical: 'INDEXABLE_CANONICAL',
  indexableAlias: 'INDEXABLE_ALIAS',
  noindexPublic: 'NOINDEX_PUBLIC',
  noindexPrivate: 'NOINDEX_PRIVATE',
  notFound: 'NOT_FOUND',
  tokenOrTransient: 'TOKEN_OR_TRANSIENT',
});

const route = ({
  id,
  path,
  title,
  description,
  classification = seoRouteClassifications.indexableCanonical,
  canonicalPath = path,
  sitemap = classification === seoRouteClassifications.indexableCanonical,
}) => Object.freeze({
  id,
  path,
  title,
  description,
  classification,
  canonicalPath,
  sitemap,
});

const staticRoutes = [
  route({
    id: 'home',
    path: '/',
    title: PUBLIC_SITE_NAME,
    description: 'Consulta las competiciones, aprende las reglas y conoce la Escuela de Galotxas y la actividad del Club Galotxes Monòver.',
  }),
  route({
    id: 'competition',
    path: '/competicion',
    title: 'Competición',
    description: 'Consulta temporadas y campeonatos públicos, calendarios, resultados y clasificaciones de Galotxas.',
  }),
  route({
    id: 'news',
    path: '/noticias',
    title: 'Noticias',
    description: 'Consulta la actualidad y la actividad pública del Club Galotxes Monòver.',
  }),
  route({
    id: 'learn',
    path: '/aprende-a-jugar',
    title: 'Aprende a jugar',
    description: 'Aprende las reglas y los conceptos esenciales de Galotxas mediante el Manual público.',
  }),
  route({
    id: 'manual',
    path: '/aprende-a-jugar/manual',
    title: 'Manual',
    description: 'Consulta el reglamento y los conceptos del Manual público de Galotxas.',
  }),
  route({
    id: 'school',
    path: schoolPath(),
    title: 'Escuela de Galotxas',
    description: 'Consulta niveles, horarios, ubicaciones e inscripciones de la Escuela de Galotxas.',
  }),
  ...Object.values(clubPages).map((page) => route({
    id: `club-${page.id}`,
    path: page.path,
    title: page.title,
    description: page.description,
  })),
  ...Object.values(legalPages).map((page) => route({
    id: `legal-${page.id}`,
    path: page.path,
    title: page.label,
    description: 'Información legal vigente del Club Galotxes Monòver.',
  })),
  route({
    id: 'tournaments',
    path: '/torneos',
    title: 'Torneos',
    description: 'Consulta los campeonatos públicos y sus recorridos deportivos.',
    classification: seoRouteClassifications.noindexPublic,
    canonicalPath: null,
    sitemap: false,
  }),
  route({
    id: 'rankings',
    path: '/rankings',
    title: 'Rankings',
    description: 'Consulta rankings públicos por histórico, temporada, campeonato y categoría con identidad minimizada.',
    classification: seoRouteClassifications.noindexPublic,
    canonicalPath: null,
    sitemap: false,
  }),
  route({
    id: 'cms-index',
    path: '/contenidos',
    title: 'Contenidos',
    description: 'Índice legado de contenidos públicos administrados por el club.',
    classification: seoRouteClassifications.noindexPublic,
    canonicalPath: null,
    sitemap: false,
  }),
  ...[
    ['login', '/login', 'Iniciar sesión'],
    ['register', '/register', 'Crear una cuenta'],
    ['forgot-password', '/forgot-password', 'Recuperar contraseña'],
    ['reset-password', '/reset-password', 'Restablecer contraseña'],
    ['player', '/player', 'Mi Panel'],
  ].map(([id, path, title]) => route({
    id,
    path,
    title,
    description: 'Área de cuenta de Galotxas.',
    classification: seoRouteClassifications.noindexPrivate,
    canonicalPath: null,
    sitemap: false,
  })),
  route({
    id: 'public-identity-confirmation',
    path: '/public-identity/confirm',
    title: 'Decisión de identidad pública',
    description: 'Ruta temporal para registrar una decisión de identidad pública.',
    classification: seoRouteClassifications.tokenOrTransient,
    canonicalPath: null,
    sitemap: false,
  }),
];

export const publicSeoRoutes = Object.freeze(staticRoutes);

export const publicSeoAliases = Object.freeze([
  route({
    id: 'legacy-about',
    path: '/nosotros',
    title: 'Quiénes somos',
    description: clubPages.about.description,
    classification: seoRouteClassifications.indexableAlias,
    canonicalPath: clubPages.about.path,
    sitemap: false,
  }),
  ...Object.values(clubPages).map((page) => route({
    id: `legacy-cms-${page.id}`,
    path: `/contenidos/${page.slug}`,
    title: page.title,
    description: page.description,
    classification: seoRouteClassifications.indexableAlias,
    canonicalPath: page.path,
    sitemap: false,
  })),
]);

const exactRoutesByPath = new Map(
  [...publicSeoRoutes, ...publicSeoAliases].map((item) => [item.path, item]),
);

export const normalizePublicPathname = (pathname) => {
  if (typeof pathname !== 'string' || pathname.length === 0) return '/';

  const withoutQueryOrHash = pathname.split(/[?#]/, 1)[0] || '/';
  const withLeadingSlash = withoutQueryOrHash.startsWith('/')
    ? withoutQueryOrHash
    : `/${withoutQueryOrHash}`;
  const withoutTrailingSlash = withLeadingSlash.length > 1
    ? withLeadingSlash.replace(/\/+$/, '')
    : withLeadingSlash;

  return withoutTrailingSlash.toLowerCase();
};

const dynamicRoute = (id, title, description, classification) => ({
  id,
  path: null,
  title,
  description,
  classification,
  canonicalPath: null,
  sitemap: false,
});

export const resolveSeoRoute = (pathname) => {
  const normalizedPathname = normalizePublicPathname(pathname);
  const exactRoute = exactRoutesByPath.get(normalizedPathname);

  if (exactRoute) return exactRoute;

  if (/^\/aprende-a-jugar\/manual\/reglamento\/[^/]+$/.test(normalizedPathname)) {
    return {
      ...dynamicRoute(
        'knowledge-regulation',
        'Documento del Reglamento',
        'Consulta un documento público del Reglamento de Galotxas.',
        seoRouteClassifications.indexableCanonical,
      ),
      canonicalPath: normalizedPathname,
    };
  }

  if (/^\/noticias\/[^/]+$/.test(normalizedPathname)) {
    return dynamicRoute(
      'news-detail',
      'Noticia',
      'Consulta una noticia pública del Club Galotxes Monòver.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/aprende-a-jugar\/manual\/conceptos\/(elementos|personas|juego)\/[^/]+$/.test(normalizedPathname)) {
    return {
      ...dynamicRoute(
        'knowledge-concept',
        'Concepto del Manual',
        'Consulta un concepto público del Manual de Galotxas.',
        seoRouteClassifications.indexableCanonical,
      ),
      canonicalPath: normalizedPathname,
    };
  }

  if (/^\/torneos\/[^/]+$/.test(normalizedPathname)) {
    return dynamicRoute(
      'championship-detail',
      'Campeonato',
      'Consulta el detalle público de un campeonato.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/categories\/[^/]+\/standings$/.test(normalizedPathname)) {
    return dynamicRoute(
      'category-standings',
      'Clasificación',
      'Consulta una clasificación pública con identidad minimizada.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/categories\/[^/]+\/schedule$/.test(normalizedPathname)) {
    return dynamicRoute(
      'category-schedule',
      'Calendario y resultados',
      'Consulta un calendario público con identidad minimizada.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/categories\/[^/]+$/.test(normalizedPathname)) {
    return dynamicRoute(
      'category-detail',
      'Categoría',
      'Consulta el resumen público de una categoría.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/matches\/[^/]+$/.test(normalizedPathname)) {
    return dynamicRoute(
      'match-detail',
      'Partido',
      'Consulta el detalle público de un partido con identidad minimizada.',
      seoRouteClassifications.noindexPublic,
    );
  }

  if (/^\/contenidos\/[^/]+$/.test(normalizedPathname)) {
    return dynamicRoute(
      'legacy-cms-page',
      'Contenido del Club',
      'Contenido público administrado por el club.',
      seoRouteClassifications.noindexPublic,
    );
  }

  return route({
    id: 'not-found',
    path: normalizedPathname,
    title: 'Página no encontrada',
    description: 'La página solicitada no está disponible en Galotxas.',
    classification: seoRouteClassifications.notFound,
    canonicalPath: null,
    sitemap: false,
  });
};

export const getCanonicalSitemapRoutes = () => publicSeoRoutes.filter((item) => item.sitemap);
