export const formatPercentage = (value, fractionDigits = 1) => {
  if (typeof value !== 'number' && typeof value !== 'string') {
    return '—';
  }

  const normalizedValue = typeof value === 'string' ? value.trim() : value;

  if (normalizedValue === '') {
    return '—';
  }

  const numericValue = Number(normalizedValue);

  if (!Number.isFinite(numericValue) || numericValue < 0 || numericValue > 100) {
    return '—';
  }

  return `${numericValue.toFixed(fractionDigits).replace('.', ',')}%`;
};
