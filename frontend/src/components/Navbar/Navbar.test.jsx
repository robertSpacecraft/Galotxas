import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useNavigate } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import styles from './Navbar.module.css';
import { Navbar } from './Navbar';

const anonymousAuth = {
  user: null,
  isAuthenticated: false,
  logout: vi.fn(),
};

const LocationChangeControl = () => {
  const navigate = useNavigate();

  return (
    <button type="button" onClick={() => navigate('/contenidos/nosotros')}>
      Cambiar ubicación
    </button>
  );
};

describe('Navbar', () => {
  it('renders only the functional editorial destinations and a separate anonymous account area', () => {
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    const editorialNavigation = screen.getByRole('list', { name: 'Navegación editorial' });
    const accountArea = screen.getByRole('group', { name: 'Cuenta' });

    expect(screen.getByRole('link', { name: 'Galotxas' })).toHaveAttribute('href', '/');
    expect(within(editorialNavigation).getAllByRole('link')).toHaveLength(2);
    expect(within(editorialNavigation).getByRole('link', { name: 'Inicio' })).toHaveAttribute('href', '/');
    expect(within(editorialNavigation).getByRole('link', { name: 'Competición' }))
      .toHaveAttribute('href', '/competicion');
    expect(within(accountArea).getByRole('link', { name: 'Iniciar sesión' }))
      .toHaveAttribute('href', '/login');

    for (const excludedName of [
      'Torneos',
      'Rankings',
      'Prensa & Media',
      'Nosotros',
      'Federaciones',
      'Contenidos',
      'Academy',
      'Aprende a jugar',
      'Escuela de Galotxas',
      'Club',
    ]) {
      expect(screen.queryByRole('link', { name: excludedName, exact: true })).not.toBeInTheDocument();
    }
  });

  it('opens and closes the accessible menu with Escape and restores focus', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });
    const toggle = screen.getByRole('button', { name: 'Abrir menú de navegación' });
    const editorialNavigation = screen.getByRole('list', { name: 'Navegación editorial' });

    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(toggle).toHaveAttribute('aria-controls', 'public-navigation');
    expect(editorialNavigation).not.toHaveClass(styles.navLinksOpen);

    await user.click(toggle);

    expect(screen.getByRole('button', { name: 'Cerrar menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'true');
    expect(editorialNavigation).toHaveClass(styles.navLinksOpen);

    screen.getByRole('link', { name: 'Competición' }).focus();
    await user.keyboard('{Escape}');

    const closedToggle = screen.getByRole('button', { name: 'Abrir menú de navegación' });
    expect(closedToggle).toHaveAttribute('aria-expanded', 'false');
    expect(editorialNavigation).not.toHaveClass(styles.navLinksOpen);
    expect(closedToggle).toHaveFocus();
  });

  it('closes the menu after selecting an editorial destination', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    await user.click(screen.getByRole('button', { name: 'Abrir menú de navegación' }));
    await user.click(screen.getByRole('link', { name: 'Competición' }));

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
  });

  it('closes the menu when the location changes outside the editorial list', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <>
        <Navbar />
        <LocationChangeControl />
      </>,
      { authValue: anonymousAuth },
    );

    await user.click(screen.getByRole('button', { name: 'Abrir menú de navegación' }));
    await user.click(screen.getByRole('button', { name: 'Cambiar ubicación' }));

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
  });

  it('keeps identity, Mi Panel and logout in the authenticated account area', async () => {
    const user = userEvent.setup();
    const logout = vi.fn();

    renderWithProviders(<Navbar />, {
      authValue: {
        user: { name: 'Robert' },
        isAuthenticated: true,
        logout,
      },
    });

    const editorialNavigation = screen.getByRole('list', { name: 'Navegación editorial' });
    const accountArea = screen.getByRole('group', { name: 'Cuenta' });

    expect(within(editorialNavigation).queryByRole('link', { name: 'Mi Panel' })).not.toBeInTheDocument();
    expect(within(accountArea).getByText(/Hola,/)).toHaveTextContent('Robert');
    expect(within(accountArea).getByRole('link', { name: 'Mi Panel' })).toHaveAttribute('href', '/player');
    expect(within(accountArea).queryByRole('link', { name: 'Iniciar sesión' })).not.toBeInTheDocument();

    await user.click(within(accountArea).getByRole('button', { name: 'Salir' }));
    expect(logout).toHaveBeenCalledOnce();
  });

  it.each([
    ['/', 'Inicio', 'page'],
    ['/competicion', 'Competición', 'page'],
    ['/torneos', 'Competición', 'location'],
    ['/torneos/12', 'Competición', 'location'],
    ['/categories/8', 'Competición', 'location'],
    ['/categories/8/standings', 'Competición', 'location'],
    ['/categories/8/schedule', 'Competición', 'location'],
    ['/matches/20', 'Competición', 'location'],
    ['/rankings', 'Competición', 'location'],
  ])('marks one active item at %s', (route, expectedName, expectedCurrent) => {
    renderWithProviders(<Navbar />, { route, authValue: anonymousAuth });

    const editorialLinks = within(
      screen.getByRole('list', { name: 'Navegación editorial' }),
    ).getAllByRole('link');
    const currentLinks = editorialLinks.filter((link) => link.hasAttribute('aria-current'));

    expect(currentLinks).toHaveLength(1);
    expect(screen.getByRole('link', { name: expectedName })).toHaveAttribute(
      'aria-current',
      expectedCurrent,
    );
    expect(screen.getByRole('link', { name: expectedName })).toHaveClass(styles.navItemActive);
  });

  it('does not activate an editorial item on a legacy institutional route', () => {
    renderWithProviders(<Navbar />, {
      route: '/contenidos/nosotros',
      authValue: anonymousAuth,
    });

    const editorialLinks = within(
      screen.getByRole('list', { name: 'Navegación editorial' }),
    ).getAllByRole('link');

    expect(editorialLinks.every((link) => !link.hasAttribute('aria-current'))).toBe(true);
    expect(editorialLinks.every((link) => !link.classList.contains(styles.navItemActive))).toBe(true);
  });
});
