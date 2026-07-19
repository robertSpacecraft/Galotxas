import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CompetitionRankingPreview } from './CompetitionRankingPreview';

const ranking = [
  {
    position: 3,
    player_id: 3,
    name: 'Tercera respuesta',
    weighted_points: 30,
    categories_played_list: ['Individual absoluta'],
  },
  { position: 1, player_id: 1, name: 'Primera respuesta', weighted_points: 50 },
  { position: null, player_id: 9, name: 'Provisional', weighted_points: 7 },
  { position: 4, player_id: 4, name: 'Cuarta respuesta', weighted_points: 20 },
  { position: 2, player_id: 2, name: 'Segunda respuesta', weighted_points: 40 },
  { position: 6, player_id: 6, name: 'Sexta respuesta', weighted_points: 10 },
];

describe('CompetitionRankingPreview', () => {
  it('preserves backend order, limits the preview to five and omits internal identifiers', () => {
    const { container } = render(<CompetitionRankingPreview ranking={ranking} />);
    const list = screen.getByRole('list', { name: 'Primeras posiciones del ranking histórico' });
    const entries = within(list).getAllByRole('listitem');

    expect(entries).toHaveLength(5);
    expect(entries.map((entry) => entry.querySelector('h3')?.textContent)).toEqual([
      'Tercera respuesta',
      'Primera respuesta',
      'Provisional',
      'Cuarta respuesta',
      'Segunda respuesta',
    ]);
    expect(screen.queryByText('Sexta respuesta')).not.toBeInTheDocument();
    expect(screen.getByText('Sin posición oficial')).toBeInTheDocument();
    expect(screen.getByText('30')).toBeInTheDocument();
    expect(screen.getByText('Categorías: Individual absoluta')).toBeInTheDocument();
    expect(container).not.toHaveTextContent('player_id');
  });

  it('omits optional values that the response does not provide', () => {
    render(<CompetitionRankingPreview ranking={[{ player_id: 10, name: 'Sin métricas' }]} />);

    expect(screen.getByText('Sin métricas')).toBeInTheDocument();
    expect(screen.getByText('Sin posición oficial')).toBeInTheDocument();
    expect(screen.queryByText(/puntos ponderados/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Categorías:/)).not.toBeInTheDocument();
  });
});
