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
  const documents = [...artifact.documents];
  const documentsById = new Map(documents.map((document) => [document.id, document]));

  const getDocumentsByCollection = (collection) => documents.filter(
    (document) => document.collection === collection,
  );

  return {
    schemaVersion: artifact.schemaVersion,
    getCollections: () => collections,
    getDocuments: () => documents,
    getCollectionsWithDocuments: () => collections
      .map((collection) => ({
        ...collection,
        documents: getDocumentsByCollection(collection.id),
      }))
      .filter((collection) => collection.documents.length > 0),
    getDocumentsByCollection,
    getDocumentById: (id) => documentsById.get(id) ?? null,
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
