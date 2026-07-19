import { describe, expect, it } from 'vitest';
import {
  getChampionshipStatusLabel,
  getChampionshipTypeLabel,
  getCompetitionDateLabel,
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

});
