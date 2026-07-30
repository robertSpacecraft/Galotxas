const dayLabels = {
  1: 'Lunes',
  2: 'Martes',
  3: 'Miércoles',
  4: 'Jueves',
  5: 'Viernes',
  6: 'Sábado',
  7: 'Domingo',
};

export const getSchoolDayLabel = (day) => dayLabels[day] ?? null;

export const getSchoolAgeRangeLabel = (minimumAge, maximumAge) => {
  if (minimumAge === null && maximumAge === null) {
    return null;
  }

  if (minimumAge !== null && maximumAge !== null) {
    return `De ${minimumAge} a ${maximumAge} años`;
  }

  return minimumAge !== null
    ? `Desde ${minimumAge} años`
    : `Hasta ${maximumAge} años`;
};
