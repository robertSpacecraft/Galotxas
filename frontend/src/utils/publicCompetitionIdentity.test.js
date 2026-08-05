import { describe, expect, it } from 'vitest';
import { getPublicCompetitionDisplayName } from './publicCompetitionIdentity';

describe('getPublicCompetitionDisplayName', () => {
  it('uses only the projection supplied by the backend', () => {
    expect(getPublicCompetitionDisplayName({
      public_display_name: '  Alias público  ',
      player: {
        name: 'Nombre privado',
        lastname: 'Apellido privado',
        nickname: 'Alias privado',
      },
    })).toBe('Alias público');
  });

  it('fails closed instead of rebuilding identity from private fields', () => {
    expect(getPublicCompetitionDisplayName({
      player: {
        name: 'Nombre privado',
        lastname: 'Apellido privado',
        email: 'private@example.test',
      },
    })).toBe('Participante');
  });
});
