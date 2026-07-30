const nullableString = (value) => (
  typeof value === 'string' && value.trim().length > 0 ? value : null
);

const normalizeLocation = (location) => {
  if (!location || typeof location !== 'object') {
    return null;
  }

  return {
    id: location.id ?? null,
    name: nullableString(location.name),
    locality: nullableString(location.locality),
    address: nullableString(location.address),
  };
};

const normalizeSchedule = (schedule) => {
  if (!schedule || typeof schedule !== 'object') {
    return null;
  }

  return {
    id: schedule.id ?? null,
    day_of_week: schedule.day_of_week ?? null,
    starts_at: nullableString(schedule.starts_at),
    ends_at: nullableString(schedule.ends_at),
    location: normalizeLocation(schedule.location),
  };
};

const normalizeLevel = (level) => {
  if (!level || typeof level !== 'object') {
    return null;
  }

  return {
    id: level.id ?? null,
    name: nullableString(level.name),
    minimum_age: Number.isInteger(level.minimum_age) ? level.minimum_age : null,
    maximum_age: Number.isInteger(level.maximum_age) ? level.maximum_age : null,
    schedules: Array.isArray(level.schedules)
      ? level.schedules.map(normalizeSchedule).filter(Boolean)
      : [],
  };
};

export const normalizeSchoolOverview = (data) => {
  if (data === null) {
    return null;
  }

  if (!data || typeof data !== 'object') {
    throw new Error('Invalid school overview response');
  }

  return {
    name: nullableString(data.name),
    enrollments_open: data.enrollments_open === true,
    contact: {
      phone: nullableString(data.contact?.phone),
      email: nullableString(data.contact?.email),
    },
    default_location: normalizeLocation(data.default_location),
    levels: Array.isArray(data.levels)
      ? data.levels.map(normalizeLevel).filter(Boolean)
      : [],
  };
};
