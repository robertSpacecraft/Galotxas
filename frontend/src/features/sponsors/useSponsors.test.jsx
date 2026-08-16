import { renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { sponsorService } from './sponsorService';
import { useSponsors } from './useSponsors';

vi.mock('./sponsorService', () => ({
  sponsorService: { getAll: vi.fn() },
}));

describe('useSponsors', () => {
  beforeEach(() => {
    sponsorService.getAll.mockReset();
  });

  it('loads content once', async () => {
    sponsorService.getAll.mockResolvedValue([{ id: 1 }]);

    const { result, rerender } = renderHook(() => useSponsors());
    rerender();

    await waitFor(() => expect(result.current.status).toBe('content'));
    expect(result.current.sponsors).toEqual([{ id: 1 }]);
    expect(sponsorService.getAll).toHaveBeenCalledOnce();
    expect(sponsorService.getAll.mock.calls[0][0].signal).toBeInstanceOf(AbortSignal);
  });

  it('turns empty, request and contract failures into non-rendering states', async () => {
    sponsorService.getAll.mockResolvedValue([]);
    const empty = renderHook(() => useSponsors());
    await waitFor(() => expect(empty.result.current.status).toBe('empty'));
    empty.unmount();

    sponsorService.getAll.mockRejectedValue(new Error('Internal detail'));
    const failed = renderHook(() => useSponsors());
    await waitFor(() => expect(failed.result.current.status).toBe('error'));
    expect(failed.result.current.sponsors).toEqual([]);
  });

  it('aborts its request when unmounted', () => {
    let signal;
    sponsorService.getAll.mockImplementation(({ signal: requestSignal }) => {
      signal = requestSignal;
      return new Promise(() => {});
    });

    const { unmount } = renderHook(() => useSponsors());
    unmount();

    expect(signal.aborted).toBe(true);
  });
});
