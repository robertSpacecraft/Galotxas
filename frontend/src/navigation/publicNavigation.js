import { clubPath } from '../features/club/clubRoutes';
import { learnPath, manualPath } from '../features/knowledge/knowledgeRoutes';
import { legalPages } from '../features/legal/legalRoutes';
import { schoolPath } from '../features/school/schoolRoutes';
import { normalizeCmsNavigationItems } from '../features/cmsNavigation/cmsNavigationContract';

export const navigationItemTypes = Object.freeze({
  link: 'link',
  disclosure: 'disclosure',
});

export const navigationAudiences = Object.freeze({
  public: 'public',
  account: 'account',
});

const freezeMatch = ({ exact = [], prefixes = [] }) => Object.freeze({
  exact: Object.freeze(exact),
  prefixes: Object.freeze(prefixes),
});

const link = ({ id, label, to, exact = [to], prefixes = [] }) => Object.freeze({
  id,
  type: navigationItemTypes.link,
  label,
  to,
  visible: true,
  audience: navigationAudiences.public,
  match: freezeMatch({ exact, prefixes }),
});

const disclosure = ({ id, label, panelId, exact = [], prefixes = [], children }) => Object.freeze({
  id,
  type: navigationItemTypes.disclosure,
  label,
  panelId,
  visible: true,
  audience: navigationAudiences.public,
  match: freezeMatch({ exact, prefixes }),
  children: Object.freeze(children),
});

const learnOverviewPath = learnPath();
const publicManualPath = manualPath();
const publicSchoolPath = schoolPath();

const competitionChildren = [
  link({ id: 'competition-overview', label: 'Vista general', to: '/competicion' }),
  link({ id: 'competition-championships', label: 'Campeonatos', to: '/torneos' }),
  link({ id: 'competition-rankings', label: 'Rankings', to: '/rankings' }),
];

const learnChildren = [
  link({
    id: 'learn-overview',
    label: 'Aprende a jugar',
    to: learnOverviewPath,
  }),
  link({
    id: 'manual',
    label: 'Manual y reglas',
    to: publicManualPath,
    prefixes: [`${publicManualPath}/`],
  }),
  link({
    id: 'school',
    label: 'Escuela de Galotxas',
    to: publicSchoolPath,
    prefixes: [`${publicSchoolPath}/`],
  }),
];

const clubChildren = [
  link({ id: 'club-about', label: 'Quiénes somos', to: clubPath('about') }),
  link({ id: 'club-contact', label: 'Contacto', to: clubPath('contact') }),
  link({ id: 'club-membership', label: 'Federarse', to: clubPath('membership') }),
  link({ id: 'club-documents', label: 'Documentos', to: clubPath('documents') }),
];

export const publicNavigation = Object.freeze([
  link({ id: 'home', label: 'Inicio', to: '/' }),
  disclosure({
    id: 'competition',
    label: 'Competición',
    panelId: 'public-navigation-competition-panel',
    exact: ['/competicion', '/torneos', '/rankings'],
    prefixes: ['/torneos/', '/categories/', '/matches/'],
    children: competitionChildren,
  }),
  link({
    id: 'news',
    label: 'Noticias',
    to: '/noticias',
    prefixes: ['/noticias/'],
  }),
  disclosure({
    id: 'learn',
    label: 'Aprende',
    panelId: 'public-navigation-learn-panel',
    exact: [learnOverviewPath, publicManualPath, publicSchoolPath],
    prefixes: [`${learnOverviewPath}/`, `${publicSchoolPath}/`],
    children: learnChildren,
  }),
  disclosure({
    id: 'club',
    label: 'Club',
    panelId: 'public-navigation-club-panel',
    exact: clubChildren.map((item) => item.to),
    prefixes: ['/club/'],
    children: clubChildren,
  }),
]);

export const composePublicNavigation = (
  structuralNavigation = publicNavigation,
  cmsPlacements = [],
) => {
  const normalizedPlacements = normalizeCmsNavigationItems(cmsPlacements);
  if (normalizedPlacements.length === 0) return structuralNavigation;

  return Object.freeze(structuralNavigation.map((item) => {
    if (item.id !== 'club' || item.type !== navigationItemTypes.disclosure) return item;

    const structuralUrls = new Set(item.children.map((child) => child.to));
    const dynamicChildren = normalizedPlacements
      .filter(({ url }) => !structuralUrls.has(url))
      .map(({ label, url }) => link({
        id: `cms-navigation:${url}`,
        label,
        to: url,
      }));

    if (dynamicChildren.length === 0) return item;

    const children = Object.freeze([...item.children, ...dynamicChildren]);

    return Object.freeze({
      ...item,
      match: freezeMatch({
        exact: [...item.match.exact, ...dynamicChildren.map(({ to }) => to)],
        prefixes: [...item.match.prefixes],
      }),
      children,
    });
  }));
};

export const publicSiteIdentity = Object.freeze({
  name: 'Club Galotxes Monòver',
});

export const publicSocialLinks = Object.freeze([
  Object.freeze({
    id: 'facebook',
    label: 'Facebook',
    href: 'https://www.facebook.com/galotxes.monover?locale=es_ES',
  }),
  Object.freeze({
    id: 'instagram',
    label: 'Instagram',
    href: 'https://www.instagram.com/clubgalotxes/',
  }),
]);

export const publicFooterNavigation = Object.freeze(
  clubChildren.map(({ id, label, to }) => Object.freeze({ id, label, to })),
);

export const publicLegalNavigation = Object.freeze(
  Object.values(legalPages).map(({ id, label, path }) => Object.freeze({ id, label, to: path })),
);

const normalizePathname = (pathname) => {
  if (typeof pathname !== 'string' || pathname.length === 0) {
    return '/';
  }

  return pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;
};

export const matchesNavigationItem = (item, pathname) => {
  const normalizedPathname = normalizePathname(pathname);

  return item.match.exact.some((path) => normalizePathname(path) === normalizedPathname)
    || item.match.prefixes.some((prefix) => normalizedPathname.startsWith(prefix));
};

export const getVisiblePublicNavigation = (navigation = publicNavigation) => navigation.filter((item) => (
  item.visible && item.audience === navigationAudiences.public
));

export const getPublicNavigationItem = (itemId, navigation = publicNavigation) => (
  navigation.find((item) => item.id === itemId) ?? null
);

export const getPublicNavigationChild = (parentId, childId, navigation = publicNavigation) => (
  getPublicNavigationItem(parentId, navigation)?.children?.find((item) => item.id === childId) ?? null
);

export const getActivePublicNavigationItem = (pathname, navigation = publicNavigation) => (
  getVisiblePublicNavigation(navigation)
    .find((item) => matchesNavigationItem(item, pathname)) ?? null
);

export const getPublicNavigationAriaCurrent = (item, pathname) => (
  item.to && normalizePathname(item.to) === normalizePathname(pathname) ? 'page' : undefined
);
