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
    expect(consoleError).toHaveBeenCalledWith('No se han podido cargar las temporadas.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');
  });
});

describe('championshipsService.getAllTimeRanking', () => {
  beforeEach(() => {
    api.get.mockReset();
  });

  it('reads the all-time ranking envelope with one request to the existing endpoint', async () => {
    const ranking = [{ position: 1, public_display_name: 'Pilotari', weighted_points: 12 }];
    api.get.mockResolvedValue({ data: { message: null, data: ranking } });

    await expect(championshipsService.getAllTimeRanking()).resolves.toEqual(ranking);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/rankings/all-time');
  });

  it('propagates ranking errors without issuing fallback requests', async () => {
    const error = new Error('Ranking unavailable');
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockRejectedValue(error);

    await expect(championshipsService.getAllTimeRanking()).rejects.toBe(error);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith('No se ha podido cargar el ranking histórico.');
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain('private@example.test');
  });
});

describe('championshipsService.getCategoryOfficialResults', () => {
  beforeEach(() => {
    api.get.mockReset();
  });

  it('reads the official-results envelope from the exact category endpoint', async () => {
    const officialResults = {
      league: {
        version: 2,
        officialized_at: '2026-09-06T10:20:30.000000Z',
        ranking: [{ position: 1, public_display_name: 'Pilotari oficial' }],
      },
      cup: null,
    };
    api.get.mockResolvedValue({ data: { message: null, data: officialResults } });

    await expect(championshipsService.getCategoryOfficialResults(12))
      .resolves.toEqual(officialResults);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(api.get).toHaveBeenCalledWith('/categories/12/official-results');
  });

  it('propagates official-results errors without issuing a fallback request', async () => {
    const error = new Error('Official result unavailable');
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    api.get.mockRejectedValue(error);

    await expect(championshipsService.getCategoryOfficialResults(12)).rejects.toBe(error);
    expect(api.get).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith(
      'No se han podido cargar los resultados oficiales de la categoría 12.',
    );
  });
});
