import { describe, expect, it } from 'vitest';
import {
  conceptDocumentPath,
  knowledgeCollectionAnchor,
  knowledgeDocumentPath,
  knowledgeDocumentFragmentPath,
  learnPath,
  manualCollectionPath,
  manualPath,
  regulationDocumentPath,
} from './knowledgeRoutes';

describe('knowledgeRoutes', () => {
  it('centraliza las rutas públicas y codifica sus parámetros', () => {
    expect(learnPath()).toBe('/aprende-a-jugar');
    expect(manualPath()).toBe('/aprende-a-jugar/manual');
    expect(knowledgeCollectionAnchor('conceptos/elementos')).toBe('manual-conceptos-elementos');
    expect(manualCollectionPath('conceptos/elementos')).toBe(
      '/aprende-a-jugar/manual#manual-conceptos-elementos',
    );
    expect(regulationDocumentPath('regla especial')).toBe(
      '/aprende-a-jugar/manual/reglamento/regla%20especial',
    );
    expect(conceptDocumentPath('elementos', 'pilota valenciana')).toBe(
      '/aprende-a-jugar/manual/conceptos/elementos/pilota%20valenciana',
    );
  });

  it('rechaza grupos, slugs y documentos no soportados', () => {
    expect(conceptDocumentPath('instalaciones', 'cancha')).toBeNull();
    expect(conceptDocumentPath('juego', '')).toBeNull();
    expect(regulationDocumentPath(null)).toBeNull();
    expect(knowledgeDocumentPath({ collection: 'otra', slug: 'doc' })).toBeNull();
    expect(manualCollectionPath('conceptos/instalaciones')).toBeNull();
    expect(knowledgeDocumentFragmentPath({ collection: 'reglamento', slug: 'saque' }, ''))
      .toBeNull();
  });

  it('resuelve reglamentos y los tres grupos de conceptos', () => {
    expect(knowledgeDocumentPath({ collection: 'reglamento', slug: 'saque' })).toBe(
      '/aprende-a-jugar/manual/reglamento/saque',
    );

    for (const group of ['elementos', 'personas', 'juego']) {
      expect(knowledgeDocumentPath({ collection: `conceptos/${group}`, slug: 'doc' })).toBe(
        `/aprende-a-jugar/manual/conceptos/${group}/doc`,
      );
    }
  });

  it('construye deep links documentales sin interpretar los headings', () => {
    expect(knowledgeDocumentFragmentPath(
      { collection: 'reglamento', slug: 'saque' },
      '2-objectiu-del-saque',
    )).toBe('/aprende-a-jugar/manual/reglamento/saque#2-objectiu-del-saque');
  });
});
