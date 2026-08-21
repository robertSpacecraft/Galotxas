import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { InvalidNewsResponseError } from './newsContract';
import { newsService } from './newsService';
import { useNewsList } from './useNewsList';

vi.mock('./newsService', () => ({
  newsService: { getList: vi.fn() },
}));

const article = (slug) => ({ slug, title: slug });
const page = (articles, currentPage, hasMore) => ({
  articles,
  meta: {
    current_page: currentPage,
    last_page: hasMore ? currentPage + 1 : currentPage,
    per_page: 12,
    total: articles.length,
    has_more: hasMore,
  },
});

describe('useNewsList', () => {
  beforeEach(() => vi.clearAllMocks());

  it('loads page one, appends later pages and deduplicates slugs', async () => {
    newsService.getList
      .mockResolvedValueOnce(page([article('dos'), article('uno')], 1, true))
      .mockResolvedValueOnce(page([article('uno'), article('cero')], 2, false));
    const { result } = renderHook(() => useNewsList());

    await waitFor(() => expect(result.current.status).toBe('content'));
    await act(() => result.current.loadMore());

    expect(result.current.articles.map(({ slug }) => slug)).toEqual(['dos', 'uno', 'cero']);
    expect(result.current.meta.current_page).toBe(2);
  });

  it('keeps existing articles when loading another page fails and supports retry', async () => {
    newsService.getList
      .mockResolvedValueOnce(page([article('dos')], 1, true))
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce(page([article('uno')], 2, false));
    const { result } = renderHook(() => useNewsList());
    await waitFor(() => expect(result.current.status).toBe('content'));

    await act(() => result.current.loadMore());
    expect(result.current.articles).toEqual([article('dos')]);
    expect(result.current.loadMoreStatus).toBe('error');

    await act(() => result.current.loadMore());
    expect(result.current.articles.map(({ slug }) => slug)).toEqual(['dos', 'uno']);
  });

  it('separates invalid contract failures and aborts the active request on unmount', async () => {
    let receivedSignal;
    newsService.getList.mockImplementation(({ signal }) => {
      receivedSignal = signal;
      return Promise.reject(new InvalidNewsResponseError());
    });
    const { result, unmount } = renderHook(() => useNewsList());

    await waitFor(() => expect(result.current.status).toBe('invalid'));
    unmount();
    expect(receivedSignal.aborted).toBe(true);
  });
});
