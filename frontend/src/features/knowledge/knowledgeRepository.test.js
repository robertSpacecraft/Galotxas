import { describe, expect, it } from 'vitest';
import { createKnowledgeRepository, knowledgeRepository } from './knowledgeRepository';

describe('knowledgeRepository', () => {
  it('expone schemaVersion, cuatro colecciones y 40 documentos en orden', () => {
    expect(knowledgeRepository.schemaVersion).toBe(1);
    expect(knowledgeRepository.getCollections().map(({ id }) => id)).toEqual([
      'reglamento',
      'conceptos/elementos',
      'conceptos/personas',
      'conceptos/juego',
    ]);
    expect(knowledgeRepository.getDocuments()).toHaveLength(40);
    expect(knowledgeRepository.getDocuments().slice(0, 8).map(({ id }) => id)).toEqual([
      'REG-001',
      'REG-002',
      'REG-003',
      'REG-004',
      'REG-005',
      'REG-006',
      'REG-007',
      'REG-008',
    ]);
  });

  it('resuelve reglamento, conceptos, ID y referencias públicas', () => {
    expect(knowledgeRepository.getRegulationBySlug('sistema-de-puntuacion')?.id).toBe('REG-006');
    expect(knowledgeRepository.getConceptByGroupAndSlug('elementos', 'pilota')?.id).toBe('CON-ELE-001');
    expect(knowledgeRepository.getConceptByGroupAndSlug('personas', 'jugador')?.id).toBe('CON-PER-001');
    expect(knowledgeRepository.getConceptByGroupAndSlug('juego', 'saque')?.id).toBe('CON-JUE-008');
    expect(knowledgeRepository.getDocumentById('REG-003')?.references.length).toBeGreaterThan(0);
  });

  it('devuelve null para valores desconocidos y omite colecciones vacías al agrupar', () => {
    expect(knowledgeRepository.getRegulationBySlug('inexistente')).toBeNull();
    expect(knowledgeRepository.getConceptByGroupAndSlug('instalaciones', 'cancha')).toBeNull();
    expect(knowledgeRepository.getDocumentById('BORRADOR-001')).toBeNull();
    expect(knowledgeRepository.getCollectionsWithDocuments()).toHaveLength(4);
    expect(knowledgeRepository.getDocumentContext('BORRADOR-001')).toBeNull();
  });

  it('resuelve posición, anterior y siguiente sólo dentro de la colección canónica', () => {
    expect(knowledgeRepository.getDocumentContext('REG-001')).toMatchObject({
      position: 1,
      total: 8,
      previousDocument: null,
      nextDocument: { id: 'REG-002' },
    });
    expect(knowledgeRepository.getDocumentContext('REG-004')).toMatchObject({
      position: 4,
      total: 8,
      previousDocument: { id: 'REG-003' },
      nextDocument: { id: 'REG-005' },
    });
    expect(knowledgeRepository.getDocumentContext('REG-008')).toMatchObject({
      position: 8,
      total: 8,
      previousDocument: { id: 'REG-007' },
      nextDocument: null,
    });

    expect(knowledgeRepository.getDocumentContext('CON-ELE-012')?.nextDocument).toBeNull();
    expect(knowledgeRepository.getDocumentContext('CON-PER-001')?.previousDocument).toBeNull();
  });

  it('no expone arrays internos mutables', () => {
    const collections = knowledgeRepository.getCollections();
    const documents = knowledgeRepository.getDocuments();
    const grouped = knowledgeRepository.getCollectionsWithDocuments();

    collections.pop();
    documents.splice(0, documents.length);
    grouped[0].documents.pop();

    expect(knowledgeRepository.getCollections()).toHaveLength(4);
    expect(knowledgeRepository.getDocuments()).toHaveLength(40);
    expect(knowledgeRepository.getDocumentsByCollection('reglamento')).toHaveLength(8);
  });

  it('trata una colección de un documento sin wrap ni cruces', () => {
    const document = {
      id: 'REG-001',
      slug: 'unico',
      title: 'Único',
      route: '/aprende-a-jugar/manual/reglamento/unico',
      collection: 'reglamento',
      order: 1,
      blocks: [],
      references: [],
    };
    const repository = createKnowledgeRepository({
      schemaVersion: 1,
      collections: [{ id: 'reglamento', title: 'Reglamento', order: 1 }],
      documents: [document],
    });

    expect(repository.getDocumentContext(document)).toMatchObject({
      position: 1,
      total: 1,
      previousDocument: null,
      nextDocument: null,
    });
  });

  it('no expone campos editoriales ni admite un artefacto público con datos privados', () => {
    for (const document of knowledgeRepository.getDocuments()) {
      expect(document).not.toHaveProperty('status');
      expect(document).not.toHaveProperty('sourcePath');
      expect(document).not.toHaveProperty('markdown');
      expect(document).not.toHaveProperty('outputPath');
    }

    expect(() => createKnowledgeRepository({
      schemaVersion: 1,
      collections: [],
      documents: [{
        id: 'PRIV-001',
        slug: 'privado',
        title: 'Privado',
        route: '/privado',
        status: 'Borrador',
        blocks: [],
        references: [],
      }],
    })).toThrow(/documento no válido/);
  });

  it('rechaza versiones de esquema desconocidas', () => {
    expect(() => createKnowledgeRepository({
      schemaVersion: 2,
      collections: [],
      documents: [],
    })).toThrow(/schemaVersion 1/);
  });
});
