import { describe, expect, it } from 'vitest';
import { formatDate, formatDateRange, UNDEFINED_DATE_LABEL } from './formatDate';

describe('formatDate', () => {
  it('formats a valid date in Spanish', () => {
    expect(formatDate('2026-07-14T10:00:00Z')).toBe('14/7/2026');
  });

  it.each([
    ['null', null],
    ['undefined', undefined],
    ['empty string', ''],
    ['invalid date', 'not-a-date'],
  ])('uses the fallback for %s', (_case, value) => {
    expect(formatDate(value)).toBe(UNDEFINED_DATE_LABEL);
  });

  it('keeps an explicit fallback in a range with only one valid date', () => {
    expect(formatDateRange('2026-07-14', null)).toBe('14/7/2026 - Sin fecha definida');
  });
});
