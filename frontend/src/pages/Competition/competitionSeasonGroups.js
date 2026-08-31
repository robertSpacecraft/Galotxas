export const MAX_HISTORICAL_SEASONS = 3;

const getSeasonRecency = (season) => {
  for (const value of [season?.end_date, season?.start_date]) {
    const timestamp = Date.parse(value);

    if (Number.isFinite(timestamp)) {
      return timestamp;
    }
  }

  return null;
};

const sortFinishedSeasons = (seasons) => seasons
  .map((season, index) => ({ season, index, recency: getSeasonRecency(season) }))
  .sort((first, second) => {
    if (first.recency !== null && second.recency !== null && first.recency !== second.recency) {
      return second.recency - first.recency;
    }

    if (first.recency !== null && second.recency === null) {
      return -1;
    }

    if (first.recency === null && second.recency !== null) {
      return 1;
    }

    return first.index - second.index;
  })
  .map(({ season }) => season);

export const groupCompetitionSeasons = (seasons) => {
  const safeSeasons = Array.isArray(seasons) ? seasons.filter(Boolean) : [];
  const activeSeasons = safeSeasons.filter((season) => season.status === 'active');
  const plannedSeasons = safeSeasons.filter((season) => season.status === 'planned');
  const recentFinishedSeasons = sortFinishedSeasons(
    safeSeasons.filter((season) => season.status === 'finished'),
  ).slice(0, MAX_HISTORICAL_SEASONS);
  const needsFinishedReference = activeSeasons.length === 0 && plannedSeasons.length === 0;
  const referenceSeason = needsFinishedReference ? recentFinishedSeasons[0] ?? null : null;
  const historicalSeasons = referenceSeason
    ? recentFinishedSeasons.slice(1)
    : recentFinishedSeasons;

  return {
    activeSeasons,
    plannedSeasons,
    referenceSeason,
    historicalSeasons,
    hasVisibleSeasons: activeSeasons.length > 0
      || plannedSeasons.length > 0
      || recentFinishedSeasons.length > 0,
  };
};
