import { describe, expect, it } from 'vitest';
import {
  COMPETITION_PATH,
  getCategoryDetailPath,
  getCategorySchedulePath,
  getCategoryStandingsPath,
  getChampionshipDetailPath,
  getMatchDetailPath,
  RANKINGS_PATH,
  TOURNAMENTS_PATH,
} from './competitionRoutes';

describe('competitionRoutes', () => {
  it('builds the existing championship and category route matrix', () => {
    expect(getChampionshipDetailPath(22)).toBe('/torneos/22');
    expect(getCategoryDetailPath(7)).toBe('/categories/7');
    expect(getCategoryStandingsPath(7)).toBe('/categories/7/standings');
    expect(getCategorySchedulePath(7)).toBe('/categories/7/schedule');
    expect(getMatchDetailPath(15)).toBe('/matches/15');
    expect([COMPETITION_PATH, TOURNAMENTS_PATH, RANKINGS_PATH]).toEqual([
      '/competicion',
      '/torneos',
      '/rankings',
    ]);
  });

  it('encodes valid identifiers without inventing routes for missing ones', () => {
    expect(getChampionshipDetailPath('trofeo estiu')).toBe('/torneos/trofeo%20estiu');
    expect(getCategoryDetailPath(null)).toBeNull();
    expect(getCategoryStandingsPath(undefined)).toBeNull();
    expect(getCategorySchedulePath('')).toBeNull();
    expect(getMatchDetailPath(null)).toBeNull();
  });
});
