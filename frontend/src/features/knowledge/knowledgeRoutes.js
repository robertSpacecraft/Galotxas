export const KNOWLEDGE_GROUPS = ['elementos', 'personas', 'juego'];

export const KNOWLEDGE_COLLECTIONS = [
  'reglamento',
  'conceptos/elementos',
  'conceptos/personas',
  'conceptos/juego',
];

const isKnownGroup = (group) => KNOWLEDGE_GROUPS.includes(group);

export const learnPath = () => '/aprende-a-jugar';

export const manualPath = () => `${learnPath()}/manual`;

export const knowledgeCollectionAnchor = (collection) => (
  KNOWLEDGE_COLLECTIONS.includes(collection)
    ? `manual-${collection.replace('/', '-')}`
    : null
);

export const manualCollectionPath = (collection) => {
  const anchor = knowledgeCollectionAnchor(collection);
  return anchor ? `${manualPath()}#${anchor}` : null;
};

export const regulationDocumentPath = (slug) => (
  typeof slug === 'string' && slug.length > 0
    ? `${manualPath()}/reglamento/${encodeURIComponent(slug)}`
    : null
);

export const conceptDocumentPath = (group, slug) => (
  isKnownGroup(group) && typeof slug === 'string' && slug.length > 0
    ? `${manualPath()}/conceptos/${encodeURIComponent(group)}/${encodeURIComponent(slug)}`
    : null
);

export const getKnowledgeDocumentGroup = (document) => {
  if (document?.collection === 'reglamento') {
    return null;
  }

  const match = /^conceptos\/(elementos|personas|juego)$/.exec(document?.collection ?? '');
  return match?.[1] ?? null;
};

export const knowledgeDocumentPath = (document) => {
  if (document?.collection === 'reglamento') {
    return regulationDocumentPath(document.slug);
  }

  const group = getKnowledgeDocumentGroup(document);
  return group ? conceptDocumentPath(group, document.slug) : null;
};

export const knowledgeDocumentFragmentPath = (document, headingId) => {
  const path = knowledgeDocumentPath(document);

  return path && typeof headingId === 'string' && headingId.length > 0
    ? `${path}#${encodeURIComponent(headingId)}`
    : null;
};

export const isKnowledgeGroup = isKnownGroup;
