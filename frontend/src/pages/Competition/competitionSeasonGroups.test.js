import { describe, expect, it } from 'vitest';
import {
  groupCompetitionSeasons,
  MAX_HISTORICAL_SEASONS,
} from './competitionSeasonGroups';

const season = (id, status, overrides = {}) => ({ id, status, ...overrides });

describe('groupCompetitionSeasons', () => {
  it('classifies only exact supported statuses and ignores unusable input safely', () => {
    const result = groupCompetitionSeasons([
      season(1, 'active'),
      season(2, 'active'),
      season(3, 'planned'),
      season(4, 'finished'),
      season(5, 'cancelled'),
      season(6, 'ACTIVE'),
      null,
    ]);

    expect(result.activeSeasons.map(({ id }) => id)).toEqual([1, 2]);
    expect(result.plannedSeasons.map(({ id }) => id)).toEqual([3]);
    expect(result.historicalSeasons.map(({ id }) => id)).toEqual([4]);
    expect(result.referenceSeason).toBeNull();
    expect(groupCompetitionSeasons(null).hasVisibleSeasons).toBe(false);
  });

  it('sorts finished seasons by reliable recency and keeps at most three', () => {
    const result = groupCompetitionSeasons([
      season(1, 'active'),
      season(2, 'finished', { end_date: '2022-12-31' }),
      season(5, 'finished', { end_date: '2025-12-31' }),
      season(4, 'finished', { start_date: '2024-01-01' }),
      season(3, 'finished', { end_date: '2023-12-31' }),
    ]);

    expect(MAX_HISTORICAL_SEASONS).toBe(3);
    expect(result.historicalSeasons.map(({ id }) => id)).toEqual([5, 4, 3]);
  });

  it('preserves API order as a deterministic fallback when dates are unavailable', () => {
    const result = groupCompetitionSeasons([
      season(1, 'planned'),
      season(9, 'finished'),
      season(7, 'finished'),
      season(8, 'finished'),
    ]);

    expect(result.historicalSeasons.map(({ id }) => id)).toEqual([9, 7, 8]);
  });

  it('extracts the latest finished season as a neutral reference only without active or planned', () => {
    const result = groupCompetitionSeasons([
      season(2, 'finished', { end_date: '2024-12-31' }),
      season(3, 'finished', { end_date: '2025-12-31' }),
      season(1, 'finished', { end_date: '2023-12-31' }),
    ]);

    expect(result.referenceSeason.id).toBe(3);
    expect(result.historicalSeasons.map(({ id }) => id)).toEqual([2, 1]);
    expect(result.hasVisibleSeasons).toBe(true);
  });
});
