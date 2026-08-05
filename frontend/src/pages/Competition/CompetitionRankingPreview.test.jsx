import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CompetitionRankingPreview } from './CompetitionRankingPreview';

const ranking = [
  {
    position: 3,
    public_display_name: 'Tercera respuesta',
    weighted_points: 30,
    categories_played_list: ['Individual absoluta'],
  },
  { position: 1, public_display_name: 'Primera respuesta', weighted_points: 50 },
  { position: null, public_display_name: 'Provisional', weighted_points: 7 },
  { position: 4, public_display_name: 'Cuarta respuesta', weighted_points: 20 },
  { position: 2, public_display_name: 'Segunda respuesta', weighted_points: 40 },
  { position: 6, public_display_name: 'Sexta respuesta', weighted_points: 10 },
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
    render(<CompetitionRankingPreview ranking={[{ public_display_name: 'Sin métricas' }]} />);

    expect(screen.getByText('Sin métricas')).toBeInTheDocument();
    expect(screen.getByText('Sin posición oficial')).toBeInTheDocument();
    expect(screen.queryByText(/puntos ponderados/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Categorías:/)).not.toBeInTheDocument();
  });
});
