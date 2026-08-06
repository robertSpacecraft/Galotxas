import { describe, expect, it } from 'vitest';
import publicLegal from '../../generated/legal/public-legal.json';
import { createLegalRepository, legalRepository } from './legalRepository';

describe('legalRepository', () => {
  it('provides exactly the public projection and no raw source data', () => {
    expect(legalRepository.getDocuments()).toHaveLength(3);
    expect(legalRepository.getDocumentById('LEG-001')).toEqual(
      expect.objectContaining({ route: '/legal/aviso-legal', version: '1.0.0' }),
    );
    expect(legalRepository.getDocumentById('LEG-999')).toBeNull();
    expect(JSON.stringify(legalRepository.getDocuments())).not.toMatch(
      /sourceDraft|source_draft|markdown|legal-drafts|knowledge\//i,
    );
  });

  it('rejects unknown documents and fields in a manipulated artifact', () => {
    const withUnknownDocument = {
      ...publicLegal,
      documents: [...publicLegal.documents, { id: 'LEG-999' }],
    };
    expect(() => createLegalRepository(withUnknownDocument)).toThrow(/schemaVersion 1/);

    const withPrivateField = structuredClone(publicLegal);
    withPrivateField.documents[0].sourceDraft = 'internal';
    expect(() => createLegalRepository(withPrivateField)).toThrow(/no cumple el contrato/);
  });
});
