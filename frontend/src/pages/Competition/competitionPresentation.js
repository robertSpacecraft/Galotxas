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

const categoryStatusLabels = {
  pending: 'Pendiente',
  active: 'Activa',
};

const categoryGenderLabels = {
  male: 'Masculina',
  female: 'Femenina',
  mixed: 'Mixta',
};

const registrationStatusLabels = {
  open: 'Abierta',
  closed: 'Cerrada',
};

const registrationRequestStatusLabels = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Rechazada',
  cancelled: 'Cancelada',
};

const matchStatusLabels = {
  scheduled: 'Programado',
  submitted: 'Pendiente de confirmación',
  validated: 'Finalizado',
  under_review: 'En revisión',
  postponed: 'Aplazado',
  cancelled: 'Cancelado',
};

const getKnownLabel = (labels, value) => labels[value] ?? null;
const getStatusFallback = (value) => (value ? 'Estado no reconocido' : 'Estado no disponible');

export const getSeasonStatusLabel = (status) => (
  getKnownLabel(seasonStatusLabels, status) ?? getStatusFallback(status)
);

export const getChampionshipStatusLabel = (status) => (
  getKnownLabel(championshipStatusLabels, status) ?? getStatusFallback(status)
);

export const getChampionshipTypeLabel = (type) => (
  getKnownLabel(championshipTypeLabels, type) ?? 'Modalidad no disponible'
);

export const getCategoryStatusLabel = (status) => (
  getKnownLabel(categoryStatusLabels, status) ?? getStatusFallback(status)
);

export const getCategoryGenderLabel = (gender) => (
  getKnownLabel(categoryGenderLabels, gender) ?? 'Categoría no especificada'
);

export const getCategoryLevelLabel = (level) => {
  if (level === null || level === undefined || level === '') {
    return 'Nivel no disponible';
  }

  return `Nivel ${level}`;
};

export const getRegistrationStatusLabel = (status) => (
  getKnownLabel(registrationStatusLabels, status) ?? getStatusFallback(status)
);

export const getRegistrationRequestStatusLabel = (status) => (
  getKnownLabel(registrationRequestStatusLabels, status) ?? getStatusFallback(status)
);

export const getMatchStatusLabel = (status) => (
  getKnownLabel(matchStatusLabels, status) ?? getStatusFallback(status)
);

export const getCompetitionDateLabel = (value) => {
  if (!value) {
    return null;
  }

  const formattedDate = formatDate(value);
  return formattedDate === UNDEFINED_DATE_LABEL ? null : formattedDate;
};

export const getCompetitionDateRangeLabel = (startDate, endDate) => {
  const startLabel = getCompetitionDateLabel(startDate);
  const endLabel = getCompetitionDateLabel(endDate);

  if (startLabel && endLabel) {
    return `Del ${startLabel} al ${endLabel}`;
  }

  if (startLabel) {
    return `Desde ${startLabel}`;
  }

  if (endLabel) {
    return `Hasta ${endLabel}`;
  }

  return null;
};

export const formatCompetitionNumber = (value, fractionDigits = 2) => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  const number = Number(value);

  if (!Number.isFinite(number)) {
    return '—';
  }

  return new Intl.NumberFormat('es-ES', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(number);
};
