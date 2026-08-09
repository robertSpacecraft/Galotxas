import { waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { createPublicSiteConfig } from './seoConfig';
import { renderWithProviders } from '../test/renderWithProviders';

const indexingEnabled = createPublicSiteConfig({
  VITE_PUBLIC_SITE_URL: 'https://example.test',
  VITE_PUBLIC_INDEXING_ENABLED: 'true',
});

const managedSelector = [
  'meta[name="description"]',
  'meta[name="robots"]',
  'link[rel="canonical"]',
  'meta[property^="og:"]',
  'script[data-public-seo-jsonld]',
].join(', ');

afterEach(() => {
  document.head.querySelectorAll(managedSelector).forEach((element) => element.remove());
});

describe('SeoProvider', () => {
  it('publishes confirmed Home metadata and a phone-free SportsClub graph', async () => {
    renderWithProviders(<h1>Inicio</h1>, { route: '/', seoConfig: indexingEnabled });

    await waitFor(() => expect(document.title).toBe('Club Galotxes Monòver'));
    expect(document.head.querySelector('link[rel="canonical"]'))
      .toHaveAttribute('href', 'https://example.test/');
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'index, follow');
    expect(document.head.querySelector('meta[property="og:site_name"]'))
      .toHaveAttribute('content', 'Club Galotxes Monòver');

    const structuredData = JSON.parse(
      document.head.querySelector('script[data-public-seo-jsonld]').textContent,
    );
    expect(structuredData).toMatchObject({
      '@type': 'SportsClub',
      name: 'Club Galotxes Monòver',
      legalName: 'Club Galotxes de Monover',
      foundingDate: '1980-03-31',
      email: 'clubgalotxesmonover@hotmail.com',
      url: 'https://example.test/',
    });
    expect(structuredData).not.toHaveProperty('telephone');
    expect(JSON.stringify(structuredData)).not.toMatch(/\+34|\b[6789]\d{8}\b/);
  });

  it('canonicalizes a maintained alias while keeping it noindex', async () => {
    renderWithProviders(<h1>Alias</h1>, {
      route: '/contenidos/nosotros/?origen=legado#historia',
      seoConfig: indexingEnabled,
    });

    await waitFor(() => {
      expect(document.head.querySelector('link[rel="canonical"]'))
        .toHaveAttribute('href', 'https://example.test/club/quienes-somos');
    });
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, follow');
    expect(document.head.querySelector('meta[property="og:url"]')).toBeNull();
  });

  it.each(['/login', '/player', '/public-identity/confirm', '/ruta-desconocida'])(
    'never gives %s a canonical or indexable metadata', async (route) => {
      renderWithProviders(<h1>Ruta no indexable</h1>, { route, seoConfig: indexingEnabled });

      await waitFor(() => {
        expect(document.head.querySelector('meta[name="robots"]'))
          .toHaveAttribute('content', expect.stringContaining('noindex'));
      });
      expect(document.head.querySelector('link[rel="canonical"]')).toBeNull();
      expect(document.head.querySelector('meta[property="og:url"]')).toBeNull();
    },
  );
});
