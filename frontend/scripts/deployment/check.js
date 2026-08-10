import { createPublicSiteConfig } from '../../src/seo/seoConfig.js';

export const PRODUCTION_SITE_URL = 'https://galotxesmonover.es';
export const PRODUCTION_API_BASE_URL = 'https://api.galotxesmonover.es/api/v1';

const localHostnames = new Set(['localhost', '0.0.0.0', '::1']);

const parseUrl = (value, key) => {
  const rawValue = String(value ?? '').trim();

  if (!rawValue) throw new Error(`${key} es obligatoria.`);

  let url;

  try {
    url = new URL(rawValue);
  } catch {
    throw new Error(`${key} debe ser una URL absoluta válida.`);
  }

  const hostname = url.hostname.toLowerCase();
  const isPlaceholder = hostname === 'example.com'
    || hostname.endsWith('.example')
    || hostname.endsWith('.test')
    || hostname.endsWith('.invalid')
    || hostname.includes('placeholder')
    || hostname.includes('change-me');

  if (
    url.protocol !== 'https:'
    || url.username
    || url.password
    || url.search
    || url.hash
    || localHostnames.has(hostname)
    || hostname.startsWith('127.')
    || hostname.endsWith('.localhost')
    || isPlaceholder
  ) {
    throw new Error(`${key} debe ser HTTPS, no local y no puede ser un placeholder.`);
  }

  return url;
};

const add = (checks, name, passed, detail) => {
  checks.push(Object.freeze({ name, passed, detail }));
};

export const checkDeploymentEnvironment = (environment = {}) => {
  const checks = [];
  const target = String(environment.DEPLOYMENT_TARGET ?? '').trim().toLowerCase();
  const releaseStage = String(
    environment.DEPLOYMENT_RELEASE_STAGE ?? 'initial',
  ).trim().toLowerCase();

  add(
    checks,
    'Entorno',
    target === 'staging' || target === 'production',
    'DEPLOYMENT_TARGET debe ser staging o production.',
  );
  add(
    checks,
    'Etapa de publicación',
    releaseStage === 'initial' || releaseStage === 'live',
    'DEPLOYMENT_RELEASE_STAGE debe ser initial o live.',
  );

  let siteUrl;
  let apiUrl;
  let siteConfiguration;

  try {
    siteUrl = parseUrl(environment.VITE_PUBLIC_SITE_URL, 'VITE_PUBLIC_SITE_URL');
    add(checks, 'URL pública', true, 'Origen HTTPS explícito.');
  } catch (error) {
    add(checks, 'URL pública', false, error.message);
  }

  try {
    apiUrl = parseUrl(environment.VITE_API_BASE_URL, 'VITE_API_BASE_URL');
    add(checks, 'URL de API', true, 'Base HTTPS explícita.');
  } catch (error) {
    add(checks, 'URL de API', false, error.message);
  }

  try {
    siteConfiguration = createPublicSiteConfig(environment);
    add(checks, 'Configuración SEO', true, 'El booleano de indexación es válido.');
  } catch (error) {
    add(checks, 'Configuración SEO', false, error.message);
  }

  if (siteUrl) {
    add(
      checks,
      'Ruta de URL pública',
      siteUrl.pathname === '/' && !siteUrl.search && !siteUrl.hash,
      'VITE_PUBLIC_SITE_URL debe ser únicamente un origen.',
    );
  }

  if (apiUrl) {
    add(
      checks,
      'Ruta API versionada',
      apiUrl.pathname === '/api/v1',
      'VITE_API_BASE_URL debe terminar exactamente en /api/v1.',
    );
  }

  if (target === 'production' && siteUrl && apiUrl) {
    add(
      checks,
      'Orígenes canónicos',
      siteUrl.origin === PRODUCTION_SITE_URL
        && `${apiUrl.origin}${apiUrl.pathname}` === PRODUCTION_API_BASE_URL,
      'Producción debe usar los dominios canónicos aprobados.',
    );
  }

  if (target === 'staging' && siteUrl && apiUrl) {
    add(
      checks,
      'Separación de staging',
      siteUrl.origin !== PRODUCTION_SITE_URL
        && apiUrl.origin !== new URL(PRODUCTION_API_BASE_URL).origin
        && siteUrl.origin !== apiUrl.origin,
      'Staging debe usar frontend, backend y datos separados de producción.',
    );
  }

  if (siteConfiguration) {
    const mustBeClosed = target === 'staging' || releaseStage === 'initial';

    add(
      checks,
      'Indexación',
      !mustBeClosed || siteConfiguration.indexingEnabled === false,
      mustBeClosed
        ? 'Staging y la primera publicación deben permanecer noindex.'
        : 'La decisión de indexación explícita es compatible con la etapa live.',
    );
  }

  return Object.freeze({
    checks: Object.freeze(checks),
    passed: checks.every((check) => check.passed),
  });
};
