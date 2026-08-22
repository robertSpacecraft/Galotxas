import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../api/client';
import { cmsNavigationService } from './cmsNavigationService';

vi.mock('../../api/client', () => ({
  default: { get: vi.fn() },
}));

describe('cmsNavigationService', () => {
  beforeEach(() => {
    api.get.mockReset();
  });

  it('requests the dedicated endpoint once with AbortSignal and returns valid items', async () => {
    const signal = new AbortController().signal;
    api.get.mockResolvedValue({
      data: {
        message: null,
        data: [{
          slot: 'club',
          label: 'Historia',
          url: '/contenidos/historia',
          sort_order: 10,
        }],
      },
    });

    await expect(cmsNavigationService.getAll({ signal })).resolves.toEqual([{
      slot: 'club',
      label: 'Historia',
      url: '/contenidos/historia',
      sort_order: 10,
    }]);
    expect(api.get).toHaveBeenCalledOnce();
    expect(api.get).toHaveBeenCalledWith('/cms-navigation', { signal });
  });

  it('preserves empty results and rejects transport or malformed payload failures', async () => {
    api.get.mockResolvedValueOnce({ data: { message: null, data: [] } });
    await expect(cmsNavigationService.getAll()).resolves.toEqual([]);

    api.get.mockRejectedValueOnce(new Error('transport detail'));
    await expect(cmsNavigationService.getAll()).rejects.toThrow('transport detail');

    api.get.mockResolvedValueOnce({ data: { data: null } });
    await expect(cmsNavigationService.getAll()).rejects.toThrow('Invalid CMS navigation response');
  });
});
