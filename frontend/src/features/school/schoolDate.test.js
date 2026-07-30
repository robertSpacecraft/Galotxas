import { describe, expect, it } from 'vitest';
import { getParticipantAgeStatus } from './schoolDate';

describe('getParticipantAgeStatus', () => {
  const reference = new Date(2026, 6, 30, 18, 0, 0);

  it('treats the eighteenth birthday as adulthood and the previous day as minority', () => {
    expect(getParticipantAgeStatus('2008-07-30', reference)).toBe('adult');
    expect(getParticipantAgeStatus('2008-07-31', reference)).toBe('minor');
  });

  it('parses local date components without UTC displacement', () => {
    expect(getParticipantAgeStatus('2012-01-01', reference)).toBe('minor');
    expect(getParticipantAgeStatus('1990-12-31', reference)).toBe('adult');
  });

  it('matches the backend no-overflow birthday rule for leap-day births', () => {
    expect(getParticipantAgeStatus('2008-02-29', new Date(2026, 1, 27))).toBe('minor');
    expect(getParticipantAgeStatus('2008-02-29', new Date(2026, 1, 28))).toBe('adult');
  });

  it.each(['', '2026-02-30', 'not-a-date', '2026-07-31'])(
    'does not decide an invalid or future date: %s',
    (birthDate) => {
      expect(getParticipantAgeStatus(birthDate, reference)).toBeNull();
    },
  );
});
