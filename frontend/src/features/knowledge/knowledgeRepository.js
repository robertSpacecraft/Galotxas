import publicKnowledge from '../../generated/knowledge/public-knowledge.json';

const SUPPORTED_SCHEMA_VERSION = 1;
const FORBIDDEN_DOCUMENT_FIELDS = ['status', 'sourcePath', 'markdown', 'outputPath'];

const containsForbiddenField = (value) => {
  if (!value || typeof value !== 'object') return false;

  return Object.entries(value).some(([key, child]) => (
    FORBIDDEN_DOCUMENT_FIELDS.includes(key) || containsForbiddenField(child)
  ));
};

const assertPublicArtifact = (artifact) => {
  if (
    artifact?.schemaVersion !== SUPPORTED_SCHEMA_VERSION
    || !Array.isArray(artifact.collections)
    || !Array.isArray(artifact.documents)
  ) {
    throw new Error('El artefacto público de Knowledge no cumple schemaVersion 1.');
  }

  for (const document of artifact.documents) {
    if (
      typeof document?.id !== 'string'
      || typeof document.slug !== 'string'
      || typeof document.title !== 'string'
      || typeof document.route !== 'string'
      || !Array.isArray(document.blocks)
      || !Array.isArray(document.references)
      || containsForbiddenField(document)
    ) {
      throw new Error('El artefacto público de Knowledge contiene un documento no válido.');
    }
  }
};

export const createKnowledgeRepository = (artifact) => {
  assertPublicArtifact(artifact);

  const collections = [...artifact.collections].sort((left, right) => left.order - right.order);
  const collectionOrder = new Map(collections.map((collection, index) => [collection.id, index]));
  const documents = [...artifact.documents].sort((left, right) => (
    (collectionOrder.get(left.collection) ?? Number.MAX_SAFE_INTEGER)
      - (collectionOrder.get(right.collection) ?? Number.MAX_SAFE_INTEGER)
    || left.order - right.order
    || left.id.localeCompare(right.id)
  ));
  const documentsById = new Map(documents.map((document) => [document.id, document]));
  const collectionsById = new Map(collections.map((collection) => [collection.id, collection]));

  const getDocumentsByCollection = (collection) => documents.filter(
    (document) => document.collection === collection,
  );

  const getDocumentContext = (documentOrId) => {
    const document = typeof documentOrId === 'string'
      ? documentsById.get(documentOrId)
      : documentOrId;

    if (!document || !documentsById.has(document.id)) {
      return null;
    }

    const collection = collectionsById.get(document.collection);
    const collectionDocuments = getDocumentsByCollection(document.collection);
    const index = collectionDocuments.findIndex(({ id }) => id === document.id);

    if (!collection || index < 0) {
      return null;
    }

    return {
      collection,
      document,
      position: index + 1,
      total: collectionDocuments.length,
      previousDocument: collectionDocuments[index - 1] ?? null,
      nextDocument: collectionDocuments[index + 1] ?? null,
    };
  };

  return {
    schemaVersion: artifact.schemaVersion,
    getCollections: () => [...collections],
    getDocuments: () => [...documents],
    getCollectionsWithDocuments: () => collections
      .map((collection) => ({
        ...collection,
        documents: getDocumentsByCollection(collection.id),
      }))
      .filter((collection) => collection.documents.length > 0),
    getDocumentsByCollection,
    getDocumentById: (id) => documentsById.get(id) ?? null,
    getDocumentContext,
    getDocumentByCollectionAndSlug: (collection, slug) => documents.find(
      (document) => document.collection === collection && document.slug === slug,
    ) ?? null,
    getRegulationBySlug: (slug) => documents.find(
      (document) => document.collection === 'reglamento' && document.slug === slug,
    ) ?? null,
    getConceptByGroupAndSlug: (group, slug) => documents.find(
      (document) => document.group === group && document.slug === slug,
    ) ?? null,
  };
};

export const knowledgeRepository = createKnowledgeRepository(publicKnowledge);
