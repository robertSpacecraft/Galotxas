import { joinPublicUrl } from '../../src/seo/seoConfig.js';
import {
  getCanonicalSitemapRoutes,
  normalizePublicPathname,
  resolveSeoRoute,
  seoRouteClassifications,
} from '../../src/seo/seoManifest.js';

const sortByPath = (left, right) => (
  left.path < right.path ? -1 : left.path > right.path ? 1 : 0
);

const assertCanonicalPath = (pathname) => {
  if (
    typeof pathname !== 'string'
    || !pathname.startsWith('/')
    || pathname.includes('?')
    || pathname.includes('#')
    || normalizePublicPathname(pathname) !== pathname
  ) {
    throw new Error(`Ruta canonical no válida: ${pathname}`);
  }

  const route = resolveSeoRoute(pathname);
  if (
    route.classification !== seoRouteClassifications.indexableCanonical
    || route.canonicalPath !== pathname
  ) {
    throw new Error(`La ruta no resuelve como canonical indexable: ${pathname}`);
  }
};

const assertDate = (value, pathname) => {
  if (value === undefined) return;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    throw new Error(`lastmod no válido para ${pathname}.`);
  }
};

export const createSitemapEntries = ({ knowledgeArtifact, legalArtifact }) => {
  const legalLastmodByRoute = new Map(
    legalArtifact.documents.map((document) => [document.route, document.publishedAt]),
  );
  const entries = [
    ...getCanonicalSitemapRoutes().map((item) => ({
      path: item.path,
      ...(legalLastmodByRoute.has(item.path)
        ? { lastmod: legalLastmodByRoute.get(item.path) }
        : {}),
    })),
    ...knowledgeArtifact.documents.map((document) => ({
      path: document.route,
      lastmod: document.lastRevision,
    })),
  ].sort(sortByPath);
  const paths = new Set();

  for (const entry of entries) {
    assertCanonicalPath(entry.path);
    assertDate(entry.lastmod, entry.path);

    if (paths.has(entry.path)) {
      throw new Error(`Ruta duplicada en sitemap: ${entry.path}`);
    }

    paths.add(entry.path);
  }

  return Object.freeze(entries.map((entry) => Object.freeze(entry)));
};

const escapeXml = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&apos;');

export const createRobotsTxt = (config) => {
  if (!config.indexingEnabled) {
    return 'User-agent: *\nDisallow: /\n';
  }

  return [
    'User-agent: *',
    'Allow: /',
    `Sitemap: ${joinPublicUrl(config.siteUrl, '/sitemap.xml')}`,
    '',
  ].join('\n');
};

export const createSitemapXml = (config, artifacts) => {
  if (!config.indexingEnabled) return null;

  const urls = createSitemapEntries(artifacts).map((entry) => [
    '  <url>',
    `    <loc>${escapeXml(joinPublicUrl(config.siteUrl, entry.path))}</loc>`,
    ...(entry.lastmod ? [`    <lastmod>${entry.lastmod}</lastmod>`] : []),
    '  </url>',
  ].join('\n'));

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls,
    '</urlset>',
    '',
  ].join('\n');
};

export const createSeoAssets = (config, artifacts) => Object.freeze({
  robots: createRobotsTxt(config),
  sitemap: createSitemapXml(config, artifacts),
});
