import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Link, Route, Routes } from 'react-router-dom';
import { createPublicSiteConfig } from '../../seo/seoConfig';
import { seoRouteClassifications } from '../../seo/seoManifest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { PageMetadata } from './PageMetadata';

const managedSelector = [
  'meta[name="description"]',
  'meta[name="robots"]',
  'link[rel="canonical"]',
  'meta[property^="og:"]',
  'script[data-public-seo-jsonld]',
].join(', ');

const indexingEnabled = createPublicSiteConfig({
  VITE_PUBLIC_SITE_URL: 'https://example.test/',
  VITE_PUBLIC_INDEXING_ENABLED: 'true',
});

describe('PageMetadata', () => {
  beforeEach(() => {
    document.head.querySelectorAll(managedSelector).forEach((element) => element.remove());
    document.title = 'Galotxas base';
  });

  afterEach(() => {
    document.head.querySelectorAll(managedSelector).forEach((element) => element.remove());
    document.title = 'Club Galotxes Monòver';
  });

  it('applies route metadata through the central provider and restores the head', async () => {
    const { unmount } = renderWithProviders(
      <PageMetadata title="Ruta de prueba" description="Descripción de prueba." />,
      { route: '/competicion' },
    );

    await waitFor(() => {
      expect(document.title).toBe('Ruta de prueba | Club Galotxes Monòver');
    });
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Descripción de prueba.',
    );
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, nofollow');
    expect(document.head.querySelector('link[rel="canonical"]')).toBeNull();

    unmount();

    expect(document.title).toBe('Galotxas base');
    expect(document.head.querySelector(managedSelector)).toBeNull();
  });

  it('keeps one canonical and one set of Open Graph tags under valid indexing config', async () => {
    renderWithProviders(
      <PageMetadata title="Competición pública" description="Descripción pública." />,
      { route: '/competicion/?preview=1#fragmento', seoConfig: indexingEnabled },
    );

    await waitFor(() => {
      expect(document.title).toBe('Competición pública | Club Galotxes Monòver');
    });
    expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);
    expect(document.head.querySelector('link[rel="canonical"]'))
      .toHaveAttribute('href', 'https://example.test/competicion');
    expect(document.head.querySelector('meta[property="og:url"]'))
      .toHaveAttribute('content', 'https://example.test/competicion');
    expect(document.head.querySelectorAll('meta[property="og:title"]')).toHaveLength(1);
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'index, follow');
  });

  it('substitutes metadata during SPA navigation and prevents 404 inheritance', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route
          path="/competicion"
          element={(
            <>
              <PageMetadata title="Competición" description="Descripción de Competición." />
              <Link to="/ruta-inexistente">Abrir ruta inexistente</Link>
            </>
          )}
        />
        <Route
          path="*"
          element={(
            <>
              <PageMetadata
                title="Página no encontrada"
                description="Descripción de error."
                classification={seoRouteClassifications.notFound}
                canonicalPath={null}
              />
              <Link to="/competicion">Volver a Competición</Link>
            </>
          )}
        />
      </Routes>,
      { route: '/competicion', seoConfig: indexingEnabled },
    );

    await waitFor(() => expect(document.title).toBe('Competición | Club Galotxes Monòver'));
    expect(document.head.querySelector('link[rel="canonical"]')).toHaveAttribute(
      'href',
      'https://example.test/competicion',
    );

    await user.click(screen.getByRole('link', { name: 'Abrir ruta inexistente' }));

    await waitFor(() => expect(document.title).toBe('Página no encontrada | Club Galotxes Monòver'));
    expect(document.head.querySelector('meta[name="description"]'))
      .toHaveAttribute('content', 'Descripción de error.');
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, nofollow');
    expect(document.head.querySelector('link[rel="canonical"]')).toBeNull();
    expect(document.head.querySelector('meta[property="og:title"]')).toBeNull();

    await user.click(screen.getByRole('link', { name: 'Volver a Competición' }));

    await waitFor(() => expect(document.title).toBe('Competición | Club Galotxes Monòver'));
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'index, follow');
  });
});
