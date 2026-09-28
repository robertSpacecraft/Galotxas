import { describe, expect, it } from 'vitest';
import {
  formatCompetitionDate,
  formatCompetitionDateTime,
  formatCompetitionTime,
} from './competitionDate';

describe('competition dates in Europe/Madrid', () => {
  it.each([
    ['2026-10-23T17:00:00+02:00', '17:00', '23 oct 2026'],
    ['2026-10-30T17:00:00+01:00', '17:00', '30 oct 2026'],
    ['2026-11-06T18:00:00+01:00', '18:00', '6 nov 2026'],
  ])('displays official civil time across DST: %s', (value, time, date) => {
    expect(formatCompetitionTime(value)).toBe(time);
    expect(formatCompetitionDate(value)).toBe(date);
    expect(formatCompetitionDateTime(value)).toBe(`${date}, ${time}`);
  });

  it('uses the competition day when the instant falls on the previous UTC day', () => {
    const value = '2026-10-23T00:15:00+02:00';
    expect(formatCompetitionDateTime(value)).toBe('23 oct 2026, 0:15');
    expect(formatCompetitionDate(new Date(value))).toBe('23 oct 2026');
  });

  it('keeps Spanish calendar headings for civil date keys', () => {
    expect(formatCompetitionDate('2026-10-30', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    })).toBe('viernes, 30 de octubre de 2026');
  });

  it.each([null, undefined, '', 'invalid'])('handles missing or invalid values: %s', (value) => {
    expect(formatCompetitionDateTime(value)).toBe('Fecha por determinar');
    expect(formatCompetitionTime(value, null)).toBeNull();
    expect(formatCompetitionDate(value, {}, 'Sin fecha')).toBe('Sin fecha');
  });
});
