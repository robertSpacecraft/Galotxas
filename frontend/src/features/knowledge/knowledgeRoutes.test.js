import { describe, expect, it } from 'vitest';
import {
  conceptDocumentPath,
  knowledgeDocumentPath,
  learnPath,
  manualPath,
  regulationDocumentPath,
} from './knowledgeRoutes';

describe('knowledgeRoutes', () => {
  it('centraliza las rutas públicas y codifica sus parámetros', () => {
    expect(learnPath()).toBe('/aprende-a-jugar');
    expect(manualPath()).toBe('/aprende-a-jugar/manual');
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
});
