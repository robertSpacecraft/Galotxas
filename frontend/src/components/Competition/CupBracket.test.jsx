import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { CupBracket } from './CupBracket';

const entry = (name) => ({ entry_type: 'player', public_display_name: name });

const cupMatch = (overrides = {}) => ({
  id: 51,
  scheduled_date: null,
  status: 'scheduled',
  home_score: null,
  away_score: null,
  home_entry: entry('Pilotari Blau'),
  away_entry: entry('Pilotari Roig'),
  winner_entry: null,
  venue: null,
  ...overrides,
});

const cupRound = (stage, matches, overrides = {}) => ({
  id: stage === 'semifinal' ? 1 : 2,
  type: 'cup',
  phase: 'cup',
  stage,
  matches,
  ...overrides,
});

describe('CupBracket', () => {
  it('shows both semifinals and explicit pending final stages', () => {
    renderWithProviders(<CupBracket rounds={[
      cupRound('semifinal', [cupMatch(), cupMatch({ id: 52 })]),
    ]} />);

    expect(screen.getByRole('heading', { name: 'Copa', level: 2 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Semifinal 1', level: 4 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Semifinal 2', level: 4 })).toBeInTheDocument();
    expect(screen.getByText('Final pendiente de generación')).toBeInTheDocument();
    expect(screen.getByText('Tercer y cuarto puesto pendiente de generación')).toBeInTheDocument();
    expect(screen.getAllByText('Fecha y hora por definir')).toHaveLength(2);
    expect(screen.getAllByText('Pista: Por definir')).toHaveLength(2);
  });

  it('keeps final stages pending while only one semifinal has an official result', () => {
    renderWithProviders(<CupBracket rounds={[
      cupRound('semifinal', [
        cupMatch({
          status: 'validated',
          home_score: 10,
          away_score: 7,
          winner_entry: entry('Pilotari Blau'),
        }),
        cupMatch({ id: 52 }),
      ]),
    ]} />);

    expect(screen.getByText(/Ganador:/)).toHaveTextContent('Pilotari Blau');
    expect(screen.getByText('Final pendiente de generación')).toBeInTheDocument();
    expect(screen.queryByText('Campeón de Copa')).not.toBeInTheDocument();
  });

  it('keeps final stages pending when both semifinals are official but not generated', () => {
    renderWithProviders(<CupBracket rounds={[
      cupRound('semifinal', [
        cupMatch({
          status: 'validated',
          home_score: 10,
          away_score: 7,
          winner_entry: entry('Pilotari Blau'),
        }),
        cupMatch({
          id: 52,
          status: 'validated',
          home_score: 6,
          away_score: 10,
          winner_entry: entry('Pilotari Roig'),
        }),
      ]),
    ]} />);

    expect(screen.getAllByText(/Ganador:/)).toHaveLength(2);
    expect(screen.getByText('Final pendiente de generación')).toBeInTheDocument();
    expect(screen.queryByText('Campeón de Copa')).not.toBeInTheDocument();
  });

  it('uses the official winner_entry for the cup champion without comparing scores', () => {
    renderWithProviders(<CupBracket rounds={[
      cupRound('semifinal', [cupMatch()]),
      cupRound('final', [cupMatch({
        id: 60,
        scheduled_date: '2026-09-20T19:15:00.000Z',
        status: 'validated',
        home_score: 10,
        away_score: 7,
        winner_entry: entry('Pilotari Roig'),
        venue: { name: 'Trinquet Final' },
      })]),
      cupRound('third_place', [cupMatch({ id: 61 })]),
    ]} />);

    expect(screen.getByText('Campeón de Copa').nextElementSibling).toHaveTextContent('Pilotari Roig');
    expect(screen.getByText(/Ganador:/)).toHaveTextContent('Pilotari Roig');
    expect(screen.getByText('Pista: Trinquet Final')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver detalle de Final' }))
      .toHaveAttribute('href', '/matches/60');
    expect(screen.getByText(/20 sept 2026/)).toHaveAttribute('datetime', '2026-09-20T19:15:00.000Z');
    expect(
      screen.getByRole('heading', { name: 'Tercer y cuarto puesto', level: 3 })
        .compareDocumentPosition(screen.getByText('Campeón de Copa'))
        & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
  });

  it('does not render an unknown cup stage', () => {
    const { container } = renderWithProviders(<CupBracket rounds={[
      cupRound('quarterfinal', [cupMatch()]),
    ]} />);

    expect(container).toBeEmptyDOMElement();
  });
});
