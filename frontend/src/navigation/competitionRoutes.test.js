import { describe, expect, it } from 'vitest';
import {
  getCategoryDetailPath,
  getCategorySchedulePath,
  getCategoryStandingsPath,
  getChampionshipDetailPath,
} from './competitionRoutes';

describe('competitionRoutes', () => {
  it('builds the existing championship and category route matrix', () => {
    expect(getChampionshipDetailPath(22)).toBe('/torneos/22');
    expect(getCategoryDetailPath(7)).toBe('/categories/7');
    expect(getCategoryStandingsPath(7)).toBe('/categories/7/standings');
    expect(getCategorySchedulePath(7)).toBe('/categories/7/schedule');
  });

  it('encodes valid identifiers without inventing routes for missing ones', () => {
    expect(getChampionshipDetailPath('trofeo estiu')).toBe('/torneos/trofeo%20estiu');
    expect(getCategoryDetailPath(null)).toBeNull();
    expect(getCategoryStandingsPath(undefined)).toBeNull();
    expect(getCategorySchedulePath('')).toBeNull();
  });
});
