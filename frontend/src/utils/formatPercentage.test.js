import { describe, expect, it } from 'vitest';
import { formatPercentage } from './formatPercentage';

describe('formatPercentage', () => {
  it.each([
    [50, '50,0%'],
    ['50', '50,0%'],
    [0, '0,0%'],
    [100, '100,0%'],
  ])('formats %p using the current percentage contract', (value, expected) => {
    expect(formatPercentage(value)).toBe(expected);
  });

  it.each([
    null,
    undefined,
    Number.NaN,
    Number.POSITIVE_INFINITY,
    -0.01,
    100.01,
  ])('returns the empty marker for invalid value %p', (value) => {
    expect(formatPercentage(value)).toBe('—');
  });
});
