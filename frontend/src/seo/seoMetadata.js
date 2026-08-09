import { joinPublicUrl } from './seoConfig.js';
import {
  normalizePublicPathname,
  PUBLIC_SITE_NAME,
  seoRouteClassifications,
} from './seoManifest.js';

const noindexFollow = new Set([
  seoRouteClassifications.indexableAlias,
  seoRouteClassifications.noindexPublic,
]);

const formatDocumentTitle = (pageTitle, pathname) => {
  if (normalizePublicPathname(pathname) === '/') return PUBLIC_SITE_NAME;

  return pageTitle === PUBLIC_SITE_NAME
    ? PUBLIC_SITE_NAME
    : `${pageTitle} | ${PUBLIC_SITE_NAME}`;
};

const getRobotsContent = (classification, indexingEnabled) => {
  if (classification === seoRouteClassifications.tokenOrTransient) {
    return 'noindex, nofollow, noarchive';
  }
  if (!indexingEnabled) return 'noindex, nofollow';
  if (classification === seoRouteClassifications.indexableCanonical) return 'index, follow';
  if (noindexFollow.has(classification)) return 'noindex, follow';

  return 'noindex, nofollow';
};

const createSportsClubJsonLd = (url) => Object.freeze({
  '@context': 'https://schema.org',
  '@type': 'SportsClub',
  name: PUBLIC_SITE_NAME,
  legalName: 'Club Galotxes de Monover',
  foundingDate: '1980-03-31',
  email: 'clubgalotxesmonover@hotmail.com',
  url,
  address: {
    '@type': 'PostalAddress',
    streetAddress: 'C/ Pierrot, 1, 1.º',
    postalCode: '03640',
    addressLocality: 'Monóvar',
    addressRegion: 'Alicante',
    addressCountry: 'ES',
  },
  sameAs: [
    'https://www.facebook.com/galotxes.monover?locale=es_ES',
    'https://www.instagram.com/clubgalotxes/',
  ],
});

export const createSeoMetadata = ({ route, pathname, config, override = null }) => {
  const classification = override?.classification ?? route.classification;
  const canonicalPath = override && Object.hasOwn(override, 'canonicalPath')
    ? override.canonicalPath
    : route.canonicalPath;
  const pageTitle = override?.title || route.title;
  const description = override?.description || route.description;
  const canonicalUrl = config.indexingEnabled && canonicalPath
    ? joinPublicUrl(config.siteUrl, canonicalPath)
    : null;
  const isIndexableCanonical = (
    classification === seoRouteClassifications.indexableCanonical
    && Boolean(canonicalUrl)
  );
  const title = formatDocumentTitle(pageTitle, pathname);

  return Object.freeze({
    title,
    description,
    robots: getRobotsContent(classification, config.indexingEnabled),
    canonicalUrl,
    openGraph: isIndexableCanonical ? Object.freeze({
      type: 'website',
      siteName: PUBLIC_SITE_NAME,
      title,
      description,
      url: canonicalUrl,
    }) : null,
    jsonLd: isIndexableCanonical && normalizePublicPathname(pathname) === '/'
      ? createSportsClubJsonLd(canonicalUrl)
      : null,
  });
};
