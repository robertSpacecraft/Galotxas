import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Navbar } from './Navbar';

const anonymousAuth = {
  user: null,
  isAuthenticated: false,
  logout: vi.fn(),
};

describe('Navbar', () => {
  it('keeps all public links and anonymous access available', () => {
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    for (const name of [
      'Inicio',
      'Torneos',
      'Rankings',
      'Prensa & Media',
      'Nosotros',
      'Federaciones',
      'Contenidos',
      'Academy',
    ]) {
      expect(screen.getByRole('link', { name, exact: true })).toBeInTheDocument();
    }

    expect(screen.getByRole('link', { name: 'Área de jugadores' })).toHaveAttribute('href', '/login');
  });

  it('opens and closes the accessible menu with its own button and Escape', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });
    const toggle = screen.getByRole('button', { name: 'Abrir menú de navegación' });

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(toggle).toHaveAttribute('aria-controls', 'public-navigation');

    await user.click(toggle);

    expect(screen.getByRole('button', { name: 'Cerrar menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'true');

    await user.keyboard('{Escape}');

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
  });

  it('closes the menu after selecting a public link', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    await user.click(screen.getByRole('button', { name: 'Abrir menú de navegación' }));
    await user.click(screen.getByRole('link', { name: 'Rankings' }));

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
  });

  it('keeps Mi Panel and logout available for an authenticated user', async () => {
    const user = userEvent.setup();
    const logout = vi.fn();

    renderWithProviders(<Navbar />, {
      authValue: {
        user: { name: 'Robert' },
        isAuthenticated: true,
        logout,
      },
    });

    expect(screen.getByRole('link', { name: 'Mi Panel' })).toHaveAttribute('href', '/player');
    expect(screen.queryByRole('link', { name: 'Área de jugadores' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Salir' }));
    expect(logout).toHaveBeenCalledOnce();
  });
});
