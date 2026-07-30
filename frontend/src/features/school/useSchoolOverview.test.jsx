import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { schoolService } from './schoolService';
import { useSchoolOverview } from './useSchoolOverview';

vi.mock('./schoolService', () => ({
  schoolService: {
    getOverview: vi.fn(),
  },
}));

describe('useSchoolOverview', () => {
  beforeEach(() => {
    schoolService.getOverview.mockReset();
  });

  it('loads content once and exposes an explicit reload', async () => {
    const school = { name: 'Escuela', levels: [] };
    schoolService.getOverview.mockResolvedValue(school);

    const { result, rerender } = renderHook(() => useSchoolOverview());
    rerender();

    expect(result.current.status).toBe('loading');
    await waitFor(() => expect(result.current.status).toBe('content'));
    expect(result.current.data).toEqual(school);
    expect(schoolService.getOverview).toHaveBeenCalledTimes(1);

    await act(async () => {
      await expect(result.current.reload()).resolves.toEqual({ ok: true, data: school });
    });
    expect(schoolService.getOverview).toHaveBeenCalledTimes(2);
  });

  it('treats data null as a valid empty state', async () => {
    schoolService.getOverview.mockResolvedValue(null);

    const { result } = renderHook(() => useSchoolOverview());

    await waitFor(() => expect(result.current.status).toBe('empty'));
    expect(result.current.error).toBeNull();
  });

  it('uses a safe error and recovers through retry', async () => {
    schoolService.getOverview
      .mockRejectedValueOnce(new Error('Internal detail'))
      .mockResolvedValueOnce({ name: 'Escuela recuperada' });

    const { result } = renderHook(() => useSchoolOverview());

    await waitFor(() => expect(result.current.status).toBe('error'));
    expect(result.current.error).toBe('No se ha podido cargar la información de la Escuela.');

    await act(async () => {
      await result.current.reload();
    });
    expect(result.current.status).toBe('content');
  });

  it('ignores a request that resolves after unmounting', async () => {
    let resolveRequest;
    const pending = new Promise((resolve) => {
      resolveRequest = resolve;
    });
    schoolService.getOverview.mockReturnValue(pending);

    const { unmount } = renderHook(() => useSchoolOverview());
    unmount();

    await act(async () => {
      resolveRequest({ name: 'Respuesta tardía' });
      await pending;
    });

    expect(schoolService.getOverview).toHaveBeenCalledOnce();
  });
});
