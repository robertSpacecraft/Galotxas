import { describe, expect, it } from 'vitest';
import {
  InvalidCategoryScheduleResponseError,
  normalizeCategorySchedule,
  selectCategoryCupRounds,
  selectCategoryLeagueRounds,
} from './categoryScheduleContract';

const match = (overrides = {}) => ({
  id: 10,
  scheduled_date: '2026-09-12T18:30:00.000Z',
  status: 'validated',
  home_score: 10,
  away_score: 7,
  home_entry: { entry_type: 'player', public_display_name: 'Pilotari Blau' },
  away_entry: { entry_type: 'player', public_display_name: 'Pilotari Roig' },
  winner_entry: { entry_type: 'player', public_display_name: 'Pilotari Blau' },
  venue: { name: 'Trinquet Central' },
  ...overrides,
});

const round = (overrides = {}) => ({
  id: 4,
  category_id: 3,
  name: 'Semifinales',
  slug: null,
  type: 'cup',
  phase: 'cup',
  stage: 'semifinal',
  order: 100,
  status: null,
  matches: [match()],
  ...overrides,
});

describe('categoryScheduleContract', () => {
  it('accepts the closed cup phase, stage and public winner contract', () => {
    const [result] = normalizeCategorySchedule([round()]);

    expect(result.stage).toBe('semifinal');
    expect(result.matches[0].winner_entry).toEqual({
      entry_type: 'player',
      public_display_name: 'Pilotari Blau',
    });
    expect(result.matches[0]).not.toHaveProperty('winner_entry_id');
  });

  it.each(['quarterfinal', 'unknown', null])('fails closed for an unknown cup stage %s', (stage) => {
    expect(normalizeCategorySchedule([round({ stage })])).toEqual([]);
  });

  it('drops a malformed cup round without breaking valid league rounds', () => {
    const league = round({
      id: 8,
      name: 'Jornada 1',
      type: 'league',
      phase: 'league',
      stage: 'matchday',
    });

    expect(normalizeCategorySchedule([
      round({ stage: 'quarterfinal' }),
      league,
    ])).toEqual([expect.objectContaining({ id: 8, type: 'league' })]);
  });

  it('rejects a non-collection response', () => {
    expect(() => normalizeCategorySchedule(null))
      .toThrow(InvalidCategoryScheduleResponseError);
  });

  it('selects league and cup rounds by exact structural metadata', () => {
    const league = round({ id: 1, type: 'league', phase: 'league', stage: 'matchday' });
    const cup = round({ id: 2, type: 'cup', phase: 'cup', stage: 'final' });
    const legacyCup = round({ id: 3, type: 'cup', phase: null, stage: null, name: 'Final Copa' });

    expect(selectCategoryLeagueRounds([league, cup, legacyCup])).toEqual([league]);
    expect(selectCategoryCupRounds([league, cup, legacyCup])).toEqual([cup]);
  });
});
