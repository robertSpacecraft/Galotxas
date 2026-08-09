import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Home } from './Home';

describe('Home', () => {
  it('uses the approved truthful interface copy and four useful journeys', () => {
    renderWithProviders(<Home />);

    expect(screen.getByRole('heading', { name: 'Galotxas en Monóvar', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByText(
      'Consulta las competiciones, aprende las reglas y conoce la Escuela de Galotxas y la actividad del Club Galotxes Monòver.',
    )).toBeInTheDocument();

    for (const heading of [
      'Competición',
      'Aprende a jugar',
      'Escuela de Galotxas',
      'Club Galotxes Monòver',
    ]) {
      expect(screen.getByRole('heading', { name: heading, level: 2 })).toBeInTheDocument();
    }

    expect(screen.queryByText('Academy', { exact: true })).not.toBeInTheDocument();
    expect(screen.queryByText(/Prensa|Federaciones|plataforma oficial/i)).not.toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('links every call to action to an existing canonical destination', () => {
    renderWithProviders(<Home />);

    const expectedLinks = [
      ['Ver competición', '/competicion'],
      ['Aprender a jugar', '/aprende-a-jugar'],
      ['Aprende a jugar', '/aprende-a-jugar'],
      ['Manual y reglas', '/aprende-a-jugar/manual'],
      ['Ver Escuela', '/escuela'],
      ['Quiénes somos', '/club/quienes-somos'],
      ['Contacto', '/club/contacto'],
    ];

    for (const [name, href] of expectedLinks) {
      expect(screen.getAllByRole('link', { name }).some((link) => (
        link.getAttribute('href') === href
      ))).toBe(true);
    }

    const cards = screen.getAllByRole('region');
    expect(cards).toHaveLength(5);
    for (const card of cards.slice(1)) {
      expect(within(card).getAllByRole('link').length).toBeGreaterThan(0);
    }
  });

  it('supports navigation through the primary Competition CTA', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/competicion" element={<h1>Competición destino</h1>} />
      </Routes>,
    );

    await user.click(screen.getAllByRole('link', { name: 'Ver competición' })[0]);
    expect(screen.getByRole('heading', { name: 'Competición destino' })).toBeInTheDocument();
  });

  it('sets basic metadata without loading remote content', () => {
    renderWithProviders(<Home />);

    expect(document.title).toBe('Club Galotxes Monòver');
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Consulta las competiciones, aprende las reglas y conoce la Escuela de Galotxas y la actividad del Club Galotxes Monòver.',
    );
  });
});
