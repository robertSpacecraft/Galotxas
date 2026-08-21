import { describe, expect, it } from 'vitest';
import {
  InvalidNewsResponseError,
  normalizeNewsArticleResponse,
  normalizeNewsListResponse,
} from './newsContract';

const summary = (overrides = {}) => ({
  slug: 'cronica-final',
  title: 'Crónica de la final',
  excerpt: 'Resumen manual de la noticia.',
  published_at: '2026-08-21T10:00:00+00:00',
  image: {
    url: 'https://api.example.test/api/v1/news/cronica-final/image',
    width: 1600,
    height: 900,
    alt: 'Pelota sobre una pista vacía.',
    credit: null,
  },
  ...overrides,
});

const listPayload = (overrides = {}) => ({
  message: null,
  data: [summary()],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
  },
  ...overrides,
});

describe('newsContract', () => {
  it('normalizes a strict list and pagination contract', () => {
    expect(normalizeNewsListResponse(listPayload())).toEqual({
      articles: [summary()],
      meta: listPayload().meta,
    });
  });

  it('normalizes detail fields without retaining unknown or private data', () => {
    const article = normalizeNewsArticleResponse({
      data: {
        ...summary(),
        body: 'Primer párrafo.\n\nSegundo párrafo.',
        seo_title: null,
        seo_description: 'Descripción SEO.',
        image_key: 'news/private.webp',
      },
    });

    expect(article.body).toContain('Segundo párrafo.');
    expect(article).not.toHaveProperty('image_key');
  });

  it.each([
    null,
    { data: {}, meta: listPayload().meta },
    { data: [summary({ title: null })], meta: listPayload().meta },
    { data: [summary({ published_at: 'not-a-date' })], meta: listPayload().meta },
    { data: [summary({ image: { ...summary().image, width: '1600' } })], meta: listPayload().meta },
    { data: [summary()], meta: { ...listPayload().meta, per_page: 20 } },
  ])('rejects malformed list payload %#', (payload) => {
    expect(() => normalizeNewsListResponse(payload)).toThrow(InvalidNewsResponseError);
  });

  it.each([
    'news/00000000-0000-4000-8000-000000000001.webp',
    'https://objects.example.test/news.webp?X-Amz-Signature=secret',
    'javascript:alert(1)',
    'https://user:pass@api.example.test/api/v1/news/cronica-final/image',
    'https://api.example.test/api/v1/news/otro-slug/image',
  ])('rejects an unsafe or unstable image URL %s', (url) => {
    const payload = listPayload({
      data: [summary({ image: { ...summary().image, url } })],
    });

    expect(() => normalizeNewsListResponse(payload)).toThrow(InvalidNewsResponseError);
  });

  it('accepts coherent out-of-range pagination returned by the API', () => {
    expect(normalizeNewsListResponse(listPayload({
      data: [],
      meta: {
        current_page: 99,
        last_page: 2,
        per_page: 12,
        total: 13,
        has_more: false,
      },
    })).meta.current_page).toBe(99);
  });
});
