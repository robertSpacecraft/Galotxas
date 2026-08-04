const routes = {
  about: {
    id: 'about',
    path: '/club/quienes-somos',
    slug: 'nosotros',
    title: 'Quiénes somos',
    description: 'Información institucional del Club Galotxes Monòver.',
  },
  contact: {
    id: 'contact',
    path: '/club/contacto',
    slug: 'contacto',
    title: 'Contacto',
    description: 'Información pública para contactar con el Club Galotxes Monòver.',
  },
  membership: {
    id: 'membership',
    path: '/club/federarse',
    slug: 'federarse',
    title: 'Federarse',
    description: 'Información pública sobre la participación federada en el club.',
  },
  documents: {
    id: 'documents',
    path: '/club/documentos',
    slug: 'documentos',
    title: 'Documentos',
    description: 'Documentación institucional publicada por el club.',
  },
};

export const clubPages = Object.freeze(
  Object.fromEntries(
    Object.entries(routes).map(([id, route]) => [id, Object.freeze(route)]),
  ),
);

export const clubPageIds = Object.freeze(Object.keys(clubPages));

export const getClubPage = (pageId) => clubPages[pageId] ?? null;

export const clubPath = (pageId) => getClubPage(pageId)?.path ?? null;
