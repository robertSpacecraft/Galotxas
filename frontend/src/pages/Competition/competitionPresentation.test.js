import { describe, expect, it } from 'vitest';
import {
  formatCompetitionNumber,
  getCategoryGenderLabel,
  getCategoryLevelLabel,
  getCategoryStatusLabel,
  getChampionshipStatusLabel,
  getChampionshipTypeLabel,
  getCompetitionDateLabel,
  getCompetitionDateRangeLabel,
  getMatchStatusLabel,
  getSeasonStatusLabel,
} from './competitionPresentation';

describe('competitionPresentation', () => {
  it('labels every SeasonStatus value without deriving state from dates', () => {
    expect([
      getSeasonStatusLabel('planned'),
      getSeasonStatusLabel('active'),
      getSeasonStatusLabel('finished'),
      getSeasonStatusLabel('cancelled'),
    ]).toEqual(['Planificada', 'Activa', 'Finalizada', 'Cancelada']);
  });

  it('labels every ChampionshipStatus and ChampionshipType value', () => {
    expect([
      getChampionshipStatusLabel('pending'),
      getChampionshipStatusLabel('active'),
      getChampionshipStatusLabel('finished'),
      getChampionshipStatusLabel('cancelled'),
    ]).toEqual(['Pendiente', 'Activo', 'Finalizado', 'Cancelado']);
    expect([
      getChampionshipTypeLabel('singles'),
      getChampionshipTypeLabel('doubles'),
    ]).toEqual(['Individual', 'Dobles']);
  });

  it('omits nullable or invalid dates instead of inventing a value', () => {
    expect(getCompetitionDateLabel(null)).toBeNull();
    expect(getCompetitionDateLabel('invalid-date')).toBeNull();
    expect(getCompetitionDateLabel('2026-07-19')).toBe('19/7/2026');
  });

  it('formats partial date ranges without empty separators or repeated fallbacks', () => {
    expect(getCompetitionDateRangeLabel('2026-07-19', '2026-07-20'))
      .toBe('Del 19/7/2026 al 20/7/2026');
    expect(getCompetitionDateRangeLabel('2026-07-19', null)).toBe('Desde 19/7/2026');
    expect(getCompetitionDateRangeLabel(null, '2026-07-20')).toBe('Hasta 20/7/2026');
    expect(getCompetitionDateRangeLabel(null, null)).toBeNull();
  });

  it('uses neutral labels for category, match and unknown enum values', () => {
    expect(getCategoryStatusLabel('active')).toBe('Activa');
    expect(getCategoryGenderLabel('mixed')).toBe('Mixta');
    expect(getCategoryLevelLabel(5)).toBe('Nivel 5');
    expect(getMatchStatusLabel('under_review')).toBe('En revisión');
    expect(getChampionshipStatusLabel('future-value')).toBe('Estado no reconocido');
    expect(getChampionshipTypeLabel('future-value')).toBe('Modalidad no disponible');
  });

  it('formats numeric API values without exposing NaN', () => {
    expect(formatCompetitionNumber('12.5')).toBe('12,50');
    expect(formatCompetitionNumber(undefined)).toBe('—');
  });

});
