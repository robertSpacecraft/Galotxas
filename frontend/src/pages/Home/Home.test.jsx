import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Home } from './Home';

describe('Home', () => {
  it('replaces the public Academy reference with the functional School destination', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/escuela" element={<h1>Escuela destino</h1>} />
      </Routes>,
    );

    expect(screen.queryByText('Academy')).not.toBeInTheDocument();
    const schoolLink = screen.getByRole('link', { name: /Escuela de Galotxas/ });
    expect(schoolLink).toHaveAttribute('href', '/escuela');

    await user.click(schoolLink);
    expect(screen.getByRole('heading', { name: 'Escuela destino' })).toBeInTheDocument();
  });
});
