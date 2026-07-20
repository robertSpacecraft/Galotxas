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
