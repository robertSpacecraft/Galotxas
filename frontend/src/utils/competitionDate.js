const COMPETITION_TIME_ZONE = 'Europe/Madrid';
const UNDEFINED_MATCH_DATE = 'Fecha por determinar';

const format = (value, options, fallback) => {
  if (!value) return fallback;

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;

  return new Intl.DateTimeFormat('es-ES', {
    ...options,
    timeZone: COMPETITION_TIME_ZONE,
  }).format(date);
};

export const formatCompetitionDate = (
  value,
  options = { dateStyle: 'medium' },
  fallback = UNDEFINED_MATCH_DATE,
) => format(value, options, fallback);

export const formatCompetitionTime = (value, fallback = UNDEFINED_MATCH_DATE) => (
  format(value, { timeStyle: 'short' }, fallback)
);

export const formatCompetitionDateTime = (value, fallback = UNDEFINED_MATCH_DATE) => (
  format(value, { dateStyle: 'medium', timeStyle: 'short' }, fallback)
);
