// @vitest-environment node
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import publicKnowledge from '../../src/generated/knowledge/public-knowledge.json';
import publicLegal from '../../src/generated/legal/public-legal.json';
import { createPublicSiteConfig } from '../../src/seo/seoConfig.js';
import { checkPublicSeo } from './check.js';
import {
  createRobotsTxt,
  createSeoAssets,
  createSitemapEntries,
  renderInitialHtmlSeo,
} from './assets.js';

const artifacts = {
  knowledgeArtifact: publicKnowledge,
  legalArtifact: publicLegal,
};
const indexHtml = readFileSync(new URL('../../index.html', import.meta.url), 'utf8');

describe('public SEO assets', () => {
  it('fails closed without a public URL and does not create a sitemap', () => {
    const config = createPublicSiteConfig({});
    const assets = createSeoAssets(config, artifacts);
    const html = renderInitialHtmlSeo(indexHtml, config);

    expect(config).toEqual({ indexingEnabled: false, siteUrl: null });
    expect(html).toContain('<meta name="robots" content="noindex, nofollow" />');
    expect(html).not.toContain('galotxas-public-seo-robots');
    expect(assets.robots).toBe('User-agent: *\nDisallow: /\n');
    expect(assets.sitemap).toBeNull();
  });

  it('requires a non-local HTTPS origin when indexing is enabled', () => {
    expect(() => createPublicSiteConfig({ VITE_PUBLIC_INDEXING_ENABLED: 'true' }))
      .toThrow(/VITE_PUBLIC_SITE_URL es obligatoria/);
    expect(() => createPublicSiteConfig({
      VITE_PUBLIC_INDEXING_ENABLED: 'true',
      VITE_PUBLIC_SITE_URL: 'http://localhost:5173',
    })).toThrow(/URL HTTPS/);
    expect(() => createPublicSiteConfig({
      VITE_PUBLIC_INDEXING_ENABLED: 'true',
      VITE_PUBLIC_SITE_URL: 'https://example.test/?preview=1',
    })).toThrow(/query string/);
  });

  it('creates deterministic canonical-only assets for an enabled test origin', () => {
    const config = createPublicSiteConfig({
      VITE_PUBLIC_INDEXING_ENABLED: 'true',
      VITE_PUBLIC_SITE_URL: 'https://example.test/',
    });
    const first = createSeoAssets(config, artifacts);
    const second = createSeoAssets(config, artifacts);
    const entries = createSitemapEntries(artifacts);
    const html = renderInitialHtmlSeo(indexHtml, config);

    expect(first).toEqual(second);
    expect(html).not.toMatch(/<meta[^>]+name=["']robots["'][^>]*>/i);
    expect(html).not.toContain('noindex');
    expect(html).not.toContain('galotxas-public-seo-robots');
    expect(first.robots).toContain('Sitemap: https://example.test/sitemap.xml');
    expect(first.sitemap).toContain('<loc>https://example.test/</loc>');
    expect(first.sitemap).toContain('<loc>https://example.test/legal/privacidad</loc>');
    expect(first.sitemap).toContain('<loc>https://example.test/noticias</loc>');
    expect(first.sitemap).not.toContain('/noticias/cronica-runtime</loc>');
    expect(first.sitemap).not.toContain('/nosotros</loc>');
    expect(first.sitemap).not.toContain('/contenidos/');
    expect(entries).toHaveLength(53);
  });

  it('runs the complete offline SEO gate', () => {
    expect(checkPublicSeo({ environment: {}, ...artifacts })).toEqual({
      declaredRoutes: 27,
      sitemapEntries: 53,
      indexingEnabled: false,
    });
    expect(createRobotsTxt({ indexingEnabled: false, siteUrl: null }))
      .not.toContain('Sitemap:');
  });
});
