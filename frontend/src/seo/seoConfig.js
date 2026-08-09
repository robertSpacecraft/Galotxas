const TRUE_VALUES = new Set(['1', 'true']);
const FALSE_VALUES = new Set(['', '0', 'false']);

const parseBoolean = (value, key) => {
  const normalized = String(value ?? '').trim().toLowerCase();

  if (TRUE_VALUES.has(normalized)) return true;
  if (FALSE_VALUES.has(normalized)) return false;

  throw new Error(`${key} debe ser true o false.`);
};

const isLocalHostname = (hostname) => (
  hostname === 'localhost'
  || hostname === '0.0.0.0'
  || hostname === '::1'
  || hostname.endsWith('.localhost')
  || hostname.startsWith('127.')
);

const parseSiteUrl = (value) => {
  const rawValue = String(value ?? '').trim();

  if (!rawValue) return null;

  let url;

  try {
    url = new URL(rawValue);
  } catch {
    throw new Error('VITE_PUBLIC_SITE_URL debe ser una URL absoluta válida.');
  }

  if (!['http:', 'https:'].includes(url.protocol)) {
    throw new Error('VITE_PUBLIC_SITE_URL debe usar HTTP o HTTPS.');
  }

  if (url.username || url.password || url.search || url.hash) {
    throw new Error('VITE_PUBLIC_SITE_URL no admite credenciales, query string ni fragmento.');
  }

  if (url.pathname !== '/' && url.pathname !== '') {
    throw new Error('VITE_PUBLIC_SITE_URL debe identificar el origen, sin una ruta adicional.');
  }

  return Object.freeze({
    href: url.origin,
    hostname: url.hostname.toLowerCase(),
    protocol: url.protocol,
  });
};

export const createPublicSiteConfig = (environment = {}) => {
  const indexingEnabled = parseBoolean(
    environment.VITE_PUBLIC_INDEXING_ENABLED,
    'VITE_PUBLIC_INDEXING_ENABLED',
  );
  const siteUrl = parseSiteUrl(environment.VITE_PUBLIC_SITE_URL);

  if (indexingEnabled && !siteUrl) {
    throw new Error(
      'VITE_PUBLIC_SITE_URL es obligatoria cuando VITE_PUBLIC_INDEXING_ENABLED=true.',
    );
  }

  if (
    indexingEnabled
    && (siteUrl.protocol !== 'https:' || isLocalHostname(siteUrl.hostname))
  ) {
    throw new Error(
      'La indexación pública exige una URL HTTPS que no sea localhost.',
    );
  }

  return Object.freeze({
    indexingEnabled,
    siteUrl: siteUrl?.href ?? null,
  });
};

export const joinPublicUrl = (siteUrl, pathname) => (
  siteUrl ? new URL(pathname, `${siteUrl}/`).href : null
);
