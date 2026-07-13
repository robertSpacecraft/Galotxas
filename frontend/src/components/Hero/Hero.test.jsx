import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Hero } from './Hero';

describe('Hero', () => {
  it('links the main CTA to the tournament list and supports user interaction', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route path="/" element={<Hero />} />
        <Route path="/torneos" element={<h1>Torneos destino</h1>} />
      </Routes>,
    );

    const cta = screen.getByRole('link', { name: 'Ver Torneos' });
    expect(cta).toHaveAttribute('href', '/torneos');

    await user.click(cta);

    expect(screen.getByRole('heading', { name: 'Torneos destino' })).toBeInTheDocument();
  });
});
