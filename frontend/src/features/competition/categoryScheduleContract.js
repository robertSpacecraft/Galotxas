export class InvalidCategoryScheduleResponseError extends Error {
  constructor() {
    super('Invalid category schedule response');
    this.name = 'InvalidCategoryScheduleResponseError';
  }
}

const CUP_STAGES = new Set(['semifinal', 'final', 'third_place']);

const normalizeEntry = (entry) => {
  if (
    !entry
    || typeof entry !== 'object'
    || !['player', 'team'].includes(entry.entry_type)
    || typeof entry.public_display_name !== 'string'
    || entry.public_display_name.trim() === ''
  ) {
    return null;
  }

  return {
    entry_type: entry.entry_type,
    public_display_name: entry.public_display_name.trim(),
  };
};

const normalizeMatch = (match) => {
  if (!match || typeof match !== 'object' || !Number.isInteger(match.id) || match.id <= 0) {
    return null;
  }

  return {
    id: match.id,
    scheduled_date: typeof match.scheduled_date === 'string' ? match.scheduled_date : null,
    status: typeof match.status === 'string' ? match.status : null,
    home_score: Number.isInteger(match.home_score) ? match.home_score : null,
    away_score: Number.isInteger(match.away_score) ? match.away_score : null,
    home_entry: normalizeEntry(match.home_entry),
    away_entry: normalizeEntry(match.away_entry),
    winner_entry: normalizeEntry(match.winner_entry),
    venue: typeof match.venue?.name === 'string' && match.venue.name.trim() !== ''
      ? { name: match.venue.name.trim() }
      : null,
  };
};

const normalizeRound = (round) => {
  if (
    !round
    || typeof round !== 'object'
    || !Number.isInteger(round.id)
    || round.id <= 0
    || !Array.isArray(round.matches)
  ) {
    return null;
  }

  const isCup = round.type === 'cup' || round.phase === 'cup';

  if (isCup && (round.type !== 'cup' || round.phase !== 'cup' || !CUP_STAGES.has(round.stage))) {
    return null;
  }

  if (!isCup && round.type !== 'league') {
    return null;
  }

  return {
    id: round.id,
    category_id: Number.isInteger(round.category_id) ? round.category_id : null,
    name: typeof round.name === 'string' && round.name.trim() !== '' ? round.name.trim() : null,
    slug: typeof round.slug === 'string' ? round.slug : null,
    type: round.type,
    phase: isCup ? 'cup' : (typeof round.phase === 'string' ? round.phase : null),
    stage: isCup ? round.stage : (typeof round.stage === 'string' ? round.stage : null),
    order: Number.isInteger(round.order) ? round.order : null,
    status: typeof round.status === 'string' ? round.status : null,
    matches: round.matches.map(normalizeMatch).filter(Boolean),
  };
};

export const normalizeCategorySchedule = (data) => {
  if (!Array.isArray(data)) {
    throw new InvalidCategoryScheduleResponseError();
  }

  return data.map(normalizeRound).filter(Boolean);
};
