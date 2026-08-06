export const legalPages = Object.freeze({
  legalNotice: Object.freeze({
    id: 'LEG-001',
    label: 'Aviso legal',
    path: '/legal/aviso-legal',
  }),
  privacy: Object.freeze({
    id: 'LEG-002',
    label: 'Privacidad',
    path: '/legal/privacidad',
  }),
  cookies: Object.freeze({
    id: 'LEG-003',
    label: 'Cookies',
    path: '/legal/cookies',
  }),
});

const legalPagesById = new Map(
  Object.values(legalPages).map((page) => [page.id, page]),
);

export const getLegalPage = (pageId) => legalPagesById.get(pageId) ?? null;

export const legalPath = (pageId) => getLegalPage(pageId)?.path ?? null;
