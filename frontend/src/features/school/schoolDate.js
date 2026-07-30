const parseDateParts = (value) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');

  if (!match) {
    return null;
  }

  const [, rawYear, rawMonth, rawDay] = match;
  const year = Number(rawYear);
  const month = Number(rawMonth);
  const day = Number(rawDay);
  const candidate = new Date(year, month - 1, day);

  if (
    candidate.getFullYear() !== year
    || candidate.getMonth() !== month - 1
    || candidate.getDate() !== day
  ) {
    return null;
  }

  return { year, month, day };
};

const compareDateParts = (left, right) => (
  left.year - right.year
  || left.month - right.month
  || left.day - right.day
);

export const getParticipantAgeStatus = (birthDate, referenceDate = new Date()) => {
  const birth = parseDateParts(birthDate);

  if (!birth || !(referenceDate instanceof Date) || Number.isNaN(referenceDate.getTime())) {
    return null;
  }

  const reference = {
    year: referenceDate.getFullYear(),
    month: referenceDate.getMonth() + 1,
    day: referenceDate.getDate(),
  };

  if (compareDateParts(birth, reference) > 0) {
    return null;
  }

  const eighteenthBirthday = {
    year: birth.year + 18,
    month: birth.month,
    day: Math.min(
      birth.day,
      new Date(birth.year + 18, birth.month, 0).getDate(),
    ),
  };

  return compareDateParts(reference, eighteenthBirthday) >= 0 ? 'adult' : 'minor';
};
