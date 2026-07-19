import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from './api';
import { championshipsService } from './championships';

vi.mock('./api', () => ({
  default: {
    get: vi.fn(),
  },
}));

describe('championshipsService.getSeasons', () => {
  beforeEach(() => {
    api.get.mockReset();
  });

  it('reads the public seasons envelope with one request to the expected endpoint', async () => {
    const seasons = [{ id: 7, name: 'Temporada 2026', championships: [] }];
    api.get.mockResolvedValue({ data: { message: null, data: seasons } });

    await expect(championshipsService.getSeasons()).resolves.toEqual(seasons);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/seasons');
  });

  it('propagates request errors without issuing fallback requests', async () => {
    const error = new Error('Network unavailable');
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockRejectedValue(error);

    await expect(championshipsService.getSeasons()).rejects.toBe(error);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith('Error fetching seasons:', error);
  });
});
