import { describe, expect, it } from 'vitest';
import {
  checkDeploymentEnvironment,
  PRODUCTION_API_BASE_URL,
  PRODUCTION_SITE_URL,
} from './check.js';

const productionEnvironment = {
  DEPLOYMENT_TARGET: 'production',
  DEPLOYMENT_RELEASE_STAGE: 'initial',
  VITE_API_BASE_URL: PRODUCTION_API_BASE_URL,
  VITE_PUBLIC_SITE_URL: PRODUCTION_SITE_URL,
  VITE_PUBLIC_INDEXING_ENABLED: 'false',
};

describe('checkDeploymentEnvironment', () => {
  it('acepta la primera publicación productiva cerrada', () => {
    expect(checkDeploymentEnvironment(productionEnvironment).passed).toBe(true);
  });

  it('bloquea indexación inicial, HTTP, localhost y placeholders', () => {
    for (const environment of [
      { ...productionEnvironment, VITE_PUBLIC_INDEXING_ENABLED: 'true' },
      { ...productionEnvironment, VITE_API_BASE_URL: 'http://api.galotxesmonover.es/api/v1' },
      { ...productionEnvironment, VITE_API_BASE_URL: 'https://localhost/api/v1' },
      { ...productionEnvironment, VITE_PUBLIC_SITE_URL: 'https://example.test' },
    ]) {
      expect(checkDeploymentEnvironment(environment).passed).toBe(false);
    }
  });

  it('exige los orígenes canónicos y la ruta versionada en producción', () => {
    expect(checkDeploymentEnvironment({
      ...productionEnvironment,
      VITE_API_BASE_URL: 'https://api.galotxesmonover.es',
    }).passed).toBe(false);

    expect(checkDeploymentEnvironment({
      ...productionEnvironment,
      VITE_PUBLIC_SITE_URL: 'https://www.galotxesmonover.es',
    }).passed).toBe(false);
  });

  it('acepta staging separado, HTTPS y noindex', () => {
    expect(checkDeploymentEnvironment({
      DEPLOYMENT_TARGET: 'staging',
      DEPLOYMENT_RELEASE_STAGE: 'initial',
      VITE_API_BASE_URL: 'https://galotxas-staging.up.railway.app/api/v1',
      VITE_PUBLIC_SITE_URL: 'https://galotxas-staging.vercel.app',
      VITE_PUBLIC_INDEXING_ENABLED: 'false',
    }).passed).toBe(true);
  });

  it('bloquea staging contra producción o indexable', () => {
    expect(checkDeploymentEnvironment({
      ...productionEnvironment,
      DEPLOYMENT_TARGET: 'staging',
    }).passed).toBe(false);
  });

  it('permite una decisión explícita de indexación tras pasar a live', () => {
    expect(checkDeploymentEnvironment({
      ...productionEnvironment,
      DEPLOYMENT_RELEASE_STAGE: 'live',
      VITE_PUBLIC_INDEXING_ENABLED: 'true',
    }).passed).toBe(true);
  });
});
