import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../api/championships';
import { useAllTimeRanking } from './useAllTimeRanking';

vi.mock('../api/championships', () => ({
  championshipsService: {
    getAllTimeRanking: vi.fn(),
  },
}));

const deferred = () => {
  let resolve;
  let reject;
  const promise = new Promise((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
};

describe('useAllTimeRanking', () => {
  beforeEach(() => {
    championshipsService.getAllTimeRanking.mockReset();
  });

  it('starts in loading and does not duplicate the request on rerender', () => {
    championshipsService.getAllTimeRanking.mockReturnValue(new Promise(() => {}));

    const { result, rerender } = renderHook(() => useAllTimeRanking());
    rerender();

    expect(result.current.status).toBe('loading');
    expect(result.current.loading).toBe(true);
    expect(result.current.data).toEqual([]);
    expect(result.current.error).toBeNull();
    expect(championshipsService.getAllTimeRanking).toHaveBeenCalledTimes(1);
  });

  it('exposes content and an explicit empty state from valid responses', async () => {
    const ranking = [{ position: 1, player_id: 1, name: 'Aina' }];
    championshipsService.getAllTimeRanking.mockResolvedValue(ranking);

    const { result, unmount } = renderHook(() => useAllTimeRanking());

    await waitFor(() => expect(result.current.status).toBe('content'));
    expect(result.current.data).toEqual(ranking);
    unmount();

    championshipsService.getAllTimeRanking.mockResolvedValue([]);
    const emptyHook = renderHook(() => useAllTimeRanking());
    await waitFor(() => expect(emptyHook.result.current.status).toBe('empty'));
    expect(emptyHook.result.current.data).toEqual([]);
  });

  it('exposes a safe error and retries without reloading the document', async () => {
    const ranking = [{ position: null, player_id: 2, name: 'Biel' }];
    championshipsService.getAllTimeRanking
      .mockRejectedValueOnce(new Error('Internal details'))
      .mockResolvedValueOnce(ranking);

    const { result } = renderHook(() => useAllTimeRanking());

    await waitFor(() => expect(result.current.status).toBe('error'));
    expect(result.current.error).toBe('No se ha podido cargar el ranking histórico.');

    await act(async () => {
      await result.current.reload();
    });

    expect(result.current.status).toBe('content');
    expect(result.current.data).toEqual(ranking);
    expect(championshipsService.getAllTimeRanking).toHaveBeenCalledTimes(2);
  });

  it('does not let a stale request overwrite the latest retry', async () => {
    const firstRequest = deferred();
    const secondRequest = deferred();
    championshipsService.getAllTimeRanking
      .mockReturnValueOnce(firstRequest.promise)
      .mockReturnValueOnce(secondRequest.promise);

    const { result } = renderHook(() => useAllTimeRanking());

    let retryPromise;
    act(() => {
      retryPromise = result.current.reload();
    });

    await act(async () => {
      secondRequest.resolve([{ position: 1, player_id: 2, name: 'Respuesta vigente' }]);
      await retryPromise;
    });
    expect(result.current.data[0].name).toBe('Respuesta vigente');

    await act(async () => {
      firstRequest.resolve([{ position: 1, player_id: 1, name: 'Respuesta obsoleta' }]);
      await firstRequest.promise;
    });
    expect(result.current.data[0].name).toBe('Respuesta vigente');
  });

  it('ignores a response that resolves after unmounting', async () => {
    const request = deferred();
    championshipsService.getAllTimeRanking.mockReturnValue(request.promise);

    const { unmount } = renderHook(() => useAllTimeRanking());
    unmount();

    await act(async () => {
      request.resolve([{ position: 1, player_id: 3, name: 'Respuesta tardía' }]);
      await request.promise;
    });

    expect(championshipsService.getAllTimeRanking).toHaveBeenCalledTimes(1);
  });
});
