import { clubPages } from '../../src/features/club/clubRoutes.js';
import { createPublicSiteConfig } from '../../src/seo/seoConfig.js';
import {
  publicSeoAliases,
  publicSeoRoutes,
  resolveSeoRoute,
  seoRouteClassifications,
} from '../../src/seo/seoManifest.js';
import { createRobotsTxt, createSitemapEntries, createSitemapXml } from './assets.js';

const phonePattern = /(?<!\d)(?:(?:\+34|0034)[ .-]?)?(?:[6789]\d{8}|[6789]\d{2}[ .-]\d{3}[ .-]\d{3})(?!\d)/;

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

export const checkPublicSeo = ({ environment, knowledgeArtifact, legalArtifact }) => {
  const activeConfig = createPublicSiteConfig(environment);
  const indexableTestConfig = createPublicSiteConfig({
    VITE_PUBLIC_SITE_URL: 'https://example.test',
    VITE_PUBLIC_INDEXING_ENABLED: 'true',
  });
  const allDeclaredRoutes = [...publicSeoRoutes, ...publicSeoAliases];
  const declaredPaths = new Set();

  for (const route of allDeclaredRoutes) {
    assert(route.title, `Falta title para ${route.path}.`);
    assert(route.description, `Falta description para ${route.path}.`);
    assert(!declaredPaths.has(route.path), `Ruta SEO duplicada: ${route.path}.`);
    declaredPaths.add(route.path);
  }

  for (const alias of publicSeoAliases) {
    const target = resolveSeoRoute(alias.canonicalPath);
    assert(
      target.classification === seoRouteClassifications.indexableCanonical,
      `El alias ${alias.path} no apunta a un canonical indexable.`,
    );
    assert(!alias.sitemap, `El alias ${alias.path} no puede entrar en el sitemap.`);
  }

  for (const route of publicSeoRoutes) {
    if (
      route.classification === seoRouteClassifications.noindexPrivate
      || route.classification === seoRouteClassifications.tokenOrTransient
    ) {
      assert(route.canonicalPath === null, `${route.path} no puede declarar canonical.`);
    }
  }

  assert(knowledgeArtifact.documents.length === 40, 'Knowledge público debe aportar 40 documentos.');
  for (const document of knowledgeArtifact.documents) {
    const route = resolveSeoRoute(document.route);
    assert(
      route.classification === seoRouteClassifications.indexableCanonical,
      `Knowledge no está representado en SEO: ${document.id}.`,
    );
    assert(route.canonicalPath === document.route, `Canonical Knowledge inválido: ${document.id}.`);
  }

  assert(legalArtifact.documents.length === 3, 'La proyección legal debe aportar tres documentos.');
  for (const document of legalArtifact.documents) {
    const route = resolveSeoRoute(document.route);
    assert(
      route.classification === seoRouteClassifications.indexableCanonical,
      `Legal no está representado en SEO: ${document.id}.`,
    );
  }

  for (const page of Object.values(clubPages)) {
    assert(
      resolveSeoRoute(page.path).classification === seoRouteClassifications.indexableCanonical,
      `Club no está representado en SEO: ${page.path}.`,
    );
  }

  assert(
    resolveSeoRoute('/public-identity/confirm').classification
      === seoRouteClassifications.tokenOrTransient,
    'La ruta de token debe ser transitoria y no indexable.',
  );
  assert(
    resolveSeoRoute('/club').classification === seoRouteClassifications.notFound,
    '/club debe continuar en 404.',
  );
  assert(
    resolveSeoRoute('/aprende').classification === seoRouteClassifications.notFound,
    '/aprende debe continuar en 404.',
  );

  const sitemapEntries = createSitemapEntries({ knowledgeArtifact, legalArtifact });
  const sitemapXml = createSitemapXml(indexableTestConfig, { knowledgeArtifact, legalArtifact });
  const sitemapPaths = new Set(sitemapEntries.map((entry) => entry.path));

  assert(sitemapEntries.length === sitemapPaths.size, 'El sitemap contiene duplicados.');
  assert(sitemapPaths.has('/'), 'Home falta en el sitemap.');
  assert(sitemapPaths.has('/competicion'), 'Competición falta en el sitemap.');
  assert(sitemapPaths.has('/club/quienes-somos'), 'Club falta en el sitemap.');
  assert(sitemapPaths.has('/legal/privacidad'), 'Legal falta en el sitemap.');
  assert(!sitemapPaths.has('/nosotros'), 'Un alias no puede entrar en el sitemap.');
  assert(!sitemapPaths.has('/rankings'), 'Rankings volátiles no deben entrar en el sitemap.');
  assert(!sitemapXml.includes('localhost'), 'El sitemap no puede contener localhost.');
  assert(!phonePattern.test(sitemapXml), 'El sitemap no puede contener teléfonos.');

  assert(
    createRobotsTxt({ indexingEnabled: false, siteUrl: null })
      === 'User-agent: *\nDisallow: /\n',
    'robots.txt debe fallar cerrado.',
  );
  assert(
    createRobotsTxt(indexableTestConfig)
      .includes('Sitemap: https://example.test/sitemap.xml'),
    'robots.txt indexable debe enlazar el sitemap absoluto.',
  );

  if (!activeConfig.indexingEnabled) {
    assert(
      createSitemapXml(activeConfig, { knowledgeArtifact, legalArtifact }) === null,
      'No debe generarse sitemap con indexación desactivada.',
    );
  }

  return Object.freeze({
    declaredRoutes: allDeclaredRoutes.length,
    sitemapEntries: sitemapEntries.length,
    indexingEnabled: activeConfig.indexingEnabled,
  });
};
