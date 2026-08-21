import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../api/client';
import { newsService } from './newsService';

vi.mock('../../api/client', () => ({
  default: { get: vi.fn() },
}));

const summary = {
  slug: 'cronica-final',
  title: 'Crónica de la final',
  excerpt: 'Resumen manual.',
  published_at: '2026-08-21T10:00:00+00:00',
  image: {
    url: 'https://api.example.test/api/v1/news/cronica-final/image',
    width: 1600,
    height: 900,
    alt: 'Pelota sobre pista vacía.',
    credit: null,
  },
};

describe('newsService', () => {
  beforeEach(() => vi.clearAllMocks());

  it('loads a paginated list and forwards AbortSignal', async () => {
    const controller = new AbortController();
    api.get.mockResolvedValue({
      data: {
        data: [summary],
        meta: {
          current_page: 2,
          last_page: 2,
          per_page: 12,
          total: 13,
          has_more: false,
        },
      },
    });

    const result = await newsService.getList({ page: 2, signal: controller.signal });

    expect(api.get).toHaveBeenCalledWith('/news', {
      params: { page: 2 },
      signal: controller.signal,
    });
    expect(result.articles[0].slug).toBe('cronica-final');
  });

  it('loads a detail by encoded slug and forwards AbortSignal', async () => {
    const controller = new AbortController();
    api.get.mockResolvedValue({
      data: {
        data: {
          ...summary,
          body: 'Cuerpo.',
          seo_title: null,
          seo_description: null,
        },
      },
    });

    await newsService.getBySlug('cronica-final', { signal: controller.signal });

    expect(api.get).toHaveBeenCalledWith('/news/cronica-final', {
      signal: controller.signal,
    });
  });
});
