import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { InvalidNewsResponseError } from './newsContract';
import { newsService } from './newsService';
import { useNewsArticle } from './useNewsArticle';

vi.mock('./newsService', () => ({
  newsService: { getBySlug: vi.fn() },
}));

describe('useNewsArticle', () => {
  beforeEach(() => vi.clearAllMocks());

  it('aborts stale slug requests and only publishes the latest response', async () => {
    const pending = [];
    newsService.getBySlug.mockImplementation((slug, { signal }) => new Promise((resolve) => {
      pending.push({ slug, signal, resolve });
    }));
    const { result, rerender } = renderHook(({ slug }) => useNewsArticle(slug), {
      initialProps: { slug: 'primera' },
    });

    rerender({ slug: 'segunda' });
    expect(pending[0].signal.aborted).toBe(true);
    await act(() => pending[1].resolve({ slug: 'segunda' }));

    expect(result.current.status).toBe('content');
    expect(result.current.article.slug).toBe('segunda');
  });

  it.each([
    [{ response: { status: 404 } }, 'not-found'],
    [new InvalidNewsResponseError(), 'invalid'],
    [new Error('network'), 'error'],
  ])('maps remote failure %# to %s', async (failure, status) => {
    newsService.getBySlug.mockRejectedValue(failure);
    const { result } = renderHook(() => useNewsArticle('noticia'));

    await waitFor(() => expect(result.current.status).toBe(status));
  });
});
