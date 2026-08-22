import { renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { cmsNavigationService } from './cmsNavigationService';
import { useCmsNavigation } from './useCmsNavigation';

vi.mock('./cmsNavigationService', () => ({
  cmsNavigationService: { getAll: vi.fn() },
}));

describe('useCmsNavigation', () => {
  beforeEach(() => {
    cmsNavigationService.getAll.mockReset();
  });

  it('loads once and preserves the same result across rerenders', async () => {
    const placements = [{ slot: 'club', label: 'Historia', url: '/contenidos/historia', sort_order: 10 }];
    cmsNavigationService.getAll.mockResolvedValue(placements);

    const { result, rerender } = renderHook(() => useCmsNavigation());
    rerender();

    await waitFor(() => expect(result.current).toEqual(placements));
    expect(cmsNavigationService.getAll).toHaveBeenCalledOnce();
    expect(cmsNavigationService.getAll.mock.calls[0][0].signal).toBeInstanceOf(AbortSignal);
  });

  it('keeps fail-soft empty output for empty and failed requests', async () => {
    cmsNavigationService.getAll.mockResolvedValueOnce([]);
    const empty = renderHook(() => useCmsNavigation());
    await waitFor(() => expect(cmsNavigationService.getAll).toHaveBeenCalledOnce());
    expect(empty.result.current).toEqual([]);
    empty.unmount();

    cmsNavigationService.getAll.mockRejectedValueOnce(new Error('internal detail'));
    const failed = renderHook(() => useCmsNavigation());
    await waitFor(() => expect(cmsNavigationService.getAll).toHaveBeenCalledTimes(2));
    expect(failed.result.current).toEqual([]);
  });

  it('aborts and ignores a pending request after unmount', () => {
    let signal;
    cmsNavigationService.getAll.mockImplementation(({ signal: requestSignal }) => {
      signal = requestSignal;
      return new Promise(() => {});
    });

    const { unmount } = renderHook(() => useCmsNavigation());
    unmount();

    expect(signal.aborted).toBe(true);
  });
});
