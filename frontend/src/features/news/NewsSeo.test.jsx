import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Route, Routes } from 'react-router-dom';
import { NotFoundPage } from '../../pages/NotFound/NotFoundPage';
import { createPublicSiteConfig } from '../../seo/seoConfig';
import { renderWithProviders } from '../../test/renderWithProviders';
import NewsDetailPage from './NewsDetailPage';
import NewsIndexPage from './NewsIndexPage';
import { useNewsArticle } from './useNewsArticle';
import { useNewsList } from './useNewsList';

vi.mock('./useNewsArticle', () => ({
  useNewsArticle: vi.fn(),
}));

vi.mock('./useNewsList', () => ({
  useNewsList: vi.fn(),
}));

const config = createPublicSiteConfig({
  VITE_PUBLIC_SITE_URL: 'https://example.test',
  VITE_PUBLIC_INDEXING_ENABLED: 'true',
});

const managedSelector = [
  'meta[name="description"]',
  'meta[name="robots"]',
  'link[rel="canonical"]',
  'meta[property^="og:"]',
  'meta[property="article:published_time"]',
  'script[data-public-seo-jsonld]',
].join(', ');

const article = {
  slug: 'cronica-final',
  title: 'Crónica de la final',
  excerpt: 'Resumen público de la final.',
  body: 'Cuerpo público.',
  published_at: '2026-08-21T10:00:00+00:00',
  seo_title: 'Final de Galotxas',
  seo_description: 'Descripción SEO editorial.',
  image: {
    url: 'https://api.example.test/api/v1/news/cronica-final/image',
    width: 1600,
    height: 900,
    alt: 'Pelota sobre una pista vacía.',
    credit: null,
  },
};

describe('News SEO', () => {
  beforeEach(() => {
    document.head.querySelectorAll(managedSelector).forEach((element) => element.remove());
    document.title = 'Galotxas base';
    useNewsList.mockReturnValue({
      articles: [],
      meta: null,
      status: 'empty',
      loadMoreStatus: 'idle',
      error: null,
      loadMoreError: null,
      reload: vi.fn(),
      loadMore: vi.fn(),
    });
  });

  afterEach(() => {
    document.head.querySelectorAll(managedSelector).forEach((element) => element.remove());
  });

  it('publishes stable canonical website metadata for the index', async () => {
    renderWithProviders(<NewsIndexPage />, { route: '/noticias', seoConfig: config });

    await waitFor(() => expect(document.title).toBe('Noticias | Club Galotxes Monòver'));
    expect(document.head.querySelector('link[rel="canonical"]'))
      .toHaveAttribute('href', 'https://example.test/noticias');
    expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'index, follow');
    expect(document.head.querySelector('meta[property="og:type"]'))
      .toHaveAttribute('content', 'website');
  });

  it('publishes article canonical, OG fields and a minimal organization-owned JSON-LD graph', async () => {
    useNewsArticle.mockReturnValue({ article, status: 'content', error: null, reload: vi.fn() });
    renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
      seoConfig: config,
    });

    await waitFor(() => expect(document.title).toBe('Final de Galotxas | Club Galotxes Monòver'));
    expect(document.head.querySelector('link[rel="canonical"]'))
      .toHaveAttribute('href', 'https://example.test/noticias/cronica-final');
    expect(document.head.querySelector('meta[property="og:type"]'))
      .toHaveAttribute('content', 'article');
    expect(document.head.querySelector('meta[property="og:image"]'))
      .toHaveAttribute('content', article.image.url);
    expect(document.head.querySelector('meta[property="article:published_time"]'))
      .toHaveAttribute('content', article.published_at);

    const jsonLd = JSON.parse(
      document.head.querySelector('script[data-public-seo-jsonld]').textContent,
    );
    expect(jsonLd).toEqual({
      '@context': 'https://schema.org',
      '@type': 'NewsArticle',
      headline: article.title,
      description: article.seo_description,
      datePublished: article.published_at,
      image: article.image.url,
      mainEntityOfPage: 'https://example.test/noticias/cronica-final',
      author: { '@type': 'Organization', name: 'Club Galotxes Monòver' },
      publisher: { '@type': 'Organization', name: 'Club Galotxes Monòver' },
    });
    expect(jsonLd).not.toHaveProperty('logo');
  });

  it.each(['loading', 'error', 'invalid'])('keeps the %s detail state noindex and article-free', async (status) => {
    useNewsArticle.mockReturnValue({
      article: null,
      status,
      error: status === 'loading' ? null : 'Error controlado.',
      reload: vi.fn(),
    });
    renderWithProviders(<NewsDetailPage />, {
      route: '/noticias/cronica-final',
      routePath: '/noticias/:slug',
      seoConfig: config,
    });

    await waitFor(() => expect(document.head.querySelector('meta[name="robots"]'))
      .toHaveAttribute('content', 'noindex, follow'));
    expect(document.head.querySelector('link[rel="canonical"]')).toBeNull();
    expect(document.head.querySelector('meta[property="og:image"]')).toBeNull();
    expect(document.head.querySelector('script[data-public-seo-jsonld]')).toBeNull();
  });

  it('cleans article metadata when navigating to a non-indexable state', async () => {
    const user = userEvent.setup();
    useNewsArticle.mockReturnValue({ article, status: 'content', error: null, reload: vi.fn() });
    renderWithProviders(
      <Routes>
        <Route path="/noticias/:slug" element={<NewsDetailPage />} />
        <Route path="/noticias" element={<NotFoundPage />} />
      </Routes>,
      { route: '/noticias/cronica-final', seoConfig: config },
    );
    await waitFor(() => expect(document.head.querySelector('meta[property="og:image"]'))
      .toHaveAttribute('content', article.image.url));

    await user.click(screen.getByRole('link', { name: 'Volver a Noticias' }));

    await waitFor(() => expect(document.title)
      .toBe('Página no encontrada | Club Galotxes Monòver'));
    expect(document.head.querySelector('link[rel="canonical"]')).toBeNull();
    expect(document.head.querySelector('meta[property="og:image"]')).toBeNull();
    expect(document.head.querySelector('meta[property="article:published_time"]')).toBeNull();
    expect(document.head.querySelector('script[data-public-seo-jsonld]')).toBeNull();
  });
});
