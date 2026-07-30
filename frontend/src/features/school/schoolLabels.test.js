import { describe, expect, it } from 'vitest';
import { getSchoolAgeRangeLabel, getSchoolDayLabel } from './schoolLabels';

describe('schoolLabels', () => {
  it('maps ISO weekdays without date APIs', () => {
    expect([1, 2, 3, 4, 5, 6, 7].map(getSchoolDayLabel)).toEqual([
      'Lunes',
      'Martes',
      'Miércoles',
      'Jueves',
      'Viernes',
      'Sábado',
      'Domingo',
    ]);
    expect(getSchoolDayLabel(8)).toBeNull();
  });

  it('only describes age limits supplied by the API', () => {
    expect(getSchoolAgeRangeLabel(null, null)).toBeNull();
    expect(getSchoolAgeRangeLabel(6, null)).toBe('Desde 6 años');
    expect(getSchoolAgeRangeLabel(null, 12)).toBe('Hasta 12 años');
    expect(getSchoolAgeRangeLabel(6, 12)).toBe('De 6 a 12 años');
  });
});
