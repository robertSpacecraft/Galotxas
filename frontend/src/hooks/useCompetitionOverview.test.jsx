import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { championshipsService } from '../api/championships';
import { useCompetitionOverview } from './useCompetitionOverview';

vi.mock('../api/championships', () => ({
  championshipsService: {
    getSeasons: vi.fn(),
  },
}));

describe('useCompetitionOverview', () => {
  beforeEach(() => {
    championshipsService.getSeasons.mockReset();
  });

  it('starts in loading and does not duplicate the request on a rerender', () => {
    championshipsService.getSeasons.mockReturnValue(new Promise(() => {}));

    const { result, rerender } = renderHook(() => useCompetitionOverview());
    rerender();

    expect(result.current.status).toBe('loading');
    expect(result.current.loading).toBe(true);
    expect(result.current.data).toEqual([]);
    expect(result.current.error).toBeNull();
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(1);
  });

  it('exposes the public seasons after a successful request', async () => {
    const seasons = [{ id: 1, name: 'Temporada activa' }];
    championshipsService.getSeasons.mockResolvedValue(seasons);

    const { result } = renderHook(() => useCompetitionOverview());

    await waitFor(() => expect(result.current.status).toBe('content'));
    expect(result.current.loading).toBe(false);
    expect(result.current.data).toEqual(seasons);
    expect(result.current.error).toBeNull();
  });

  it('uses an explicit empty state for a valid response without seasons', async () => {
    championshipsService.getSeasons.mockResolvedValue([]);

    const { result } = renderHook(() => useCompetitionOverview());

    await waitFor(() => expect(result.current.status).toBe('empty'));
    expect(result.current.data).toEqual([]);
    expect(result.current.loading).toBe(false);
  });

  it('exposes a safe error and retries without reloading the document', async () => {
    const seasons = [{ id: 2, name: 'Temporada recuperada' }];
    championshipsService.getSeasons
      .mockRejectedValueOnce(new Error('Internal details'))
      .mockResolvedValueOnce(seasons);

    const { result } = renderHook(() => useCompetitionOverview());

    await waitFor(() => expect(result.current.status).toBe('error'));
    expect(result.current.error).toBe('No se han podido cargar las temporadas y campeonatos.');
    expect(result.current.data).toEqual([]);

    await act(async () => {
      await result.current.reload();
    });

    expect(result.current.status).toBe('content');
    expect(result.current.data).toEqual(seasons);
    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(2);
  });

  it('ignores a response that resolves after unmounting', async () => {
    let resolveRequest;
    const pendingRequest = new Promise((resolve) => {
      resolveRequest = resolve;
    });
    championshipsService.getSeasons.mockReturnValue(pendingRequest);

    const { unmount } = renderHook(() => useCompetitionOverview());
    unmount();

    await act(async () => {
      resolveRequest([{ id: 3, name: 'Respuesta tardía' }]);
      await pendingRequest;
    });

    expect(championshipsService.getSeasons).toHaveBeenCalledTimes(1);
  });
});
