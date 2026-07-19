const competitionRoutePatterns = [
  /^\/competicion\/?$/,
  /^\/torneos(?:\/[^/]+)?\/?$/,
  /^\/categories\/[^/]+(?:\/(?:standings|schedule))?\/?$/,
  /^\/matches\/[^/]+\/?$/,
  /^\/rankings\/?$/,
];

export const publicNavigation = [
  {
    id: 'home',
    label: 'Inicio',
    to: '/',
    matches: (pathname) => pathname === '/',
  },
  {
    id: 'competition',
    label: 'Competición',
    to: '/competicion',
    matches: (pathname) => competitionRoutePatterns.some((pattern) => pattern.test(pathname)),
  },
];

export const getActivePublicNavigationItem = (pathname) => (
  publicNavigation.find((item) => item.matches(pathname)) ?? null
);

export const getPublicNavigationAriaCurrent = (item, pathname) => {
  if (!item.matches(pathname)) {
    return undefined;
  }

  return pathname === item.to ? 'page' : 'location';
};
