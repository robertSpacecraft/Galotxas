import { formatDate, UNDEFINED_DATE_LABEL } from '../../utils/formatDate';

const seasonStatusLabels = {
  planned: 'Planificada',
  active: 'Activa',
  finished: 'Finalizada',
  cancelled: 'Cancelada',
};

const championshipStatusLabels = {
  pending: 'Pendiente',
  active: 'Activo',
  finished: 'Finalizado',
  cancelled: 'Cancelado',
};

const championshipTypeLabels = {
  singles: 'Individual',
  doubles: 'Dobles',
};

const getKnownLabel = (labels, value) => labels[value] ?? null;

export const getSeasonStatusLabel = (status) => (
  getKnownLabel(seasonStatusLabels, status) ?? 'Estado no disponible'
);

export const getChampionshipStatusLabel = (status) => (
  getKnownLabel(championshipStatusLabels, status) ?? 'Estado no disponible'
);

export const getChampionshipTypeLabel = (type) => getKnownLabel(championshipTypeLabels, type);

export const getChampionshipDetailPath = (championshipId) => (
  `/torneos/${encodeURIComponent(championshipId)}`
);

export const getCompetitionDateLabel = (value) => {
  if (!value) {
    return null;
  }

  const formattedDate = formatDate(value);
  return formattedDate === UNDEFINED_DATE_LABEL ? null : formattedDate;
};
