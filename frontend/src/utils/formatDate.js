export const UNDEFINED_DATE_LABEL = 'Sin fecha definida';

export const formatDate = (value) => {
  if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) {
    return UNDEFINED_DATE_LABEL;
  }

  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    return UNDEFINED_DATE_LABEL;
  }

  return new Intl.DateTimeFormat('es-ES').format(date);
};

export const formatDateRange = (startDate, endDate) => (
  `${formatDate(startDate)} - ${formatDate(endDate)}`
);
