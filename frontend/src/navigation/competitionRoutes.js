const encodeRouteIdentifier = (identifier) => {
  if (identifier === null || identifier === undefined || identifier === '') {
    return null;
  }

  return encodeURIComponent(String(identifier));
};

export const getChampionshipDetailPath = (championshipId) => {
  const identifier = encodeRouteIdentifier(championshipId);

  return identifier ? `/torneos/${identifier}` : null;
};

export const getCategoryDetailPath = (categoryId) => {
  const identifier = encodeRouteIdentifier(categoryId);

  return identifier ? `/categories/${identifier}` : null;
};

export const getCategoryStandingsPath = (categoryId) => {
  const identifier = encodeRouteIdentifier(categoryId);

  return identifier ? `/categories/${identifier}/standings` : null;
};

export const getCategorySchedulePath = (categoryId) => {
  const identifier = encodeRouteIdentifier(categoryId);

  return identifier ? `/categories/${identifier}/schedule` : null;
};
