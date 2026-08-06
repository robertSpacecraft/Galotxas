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

const openMainMenu = async (user) => {
  await user.click(screen.getByRole('button', { name: 'Abrir menú de navegación' }));
};

describe('Navbar', () => {
  it('renders the grouped editorial tree, a skip link and a separate anonymous account area', () => {
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    const navigation = screen.getByRole('navigation', { name: 'Navegación principal' });
    const editorialNavigation = screen.getByRole('list', { name: 'Navegación editorial' });
    const accountArea = screen.getByRole('group', { name: 'Cuenta' });

    expect(screen.getByRole('banner')).toContainElement(navigation);
    expect(screen.getByRole('link', { name: 'Saltar al contenido principal' }))
      .toHaveAttribute('href', '#main-content');
    expect(screen.getByRole('link', { name: 'Galotxas' })).toHaveAttribute('href', '/');
    expect(within(editorialNavigation).getAllByRole('listitem', { hidden: true })).toHaveLength(11);
    expect(within(editorialNavigation).getByRole('link', { name: 'Inicio' }))
      .toHaveAttribute('href', '/');
    expect(within(editorialNavigation).getByRole('link', { name: 'Competición' }))
      .toHaveAttribute('href', '/competicion');
    expect(within(editorialNavigation).getByRole('button', { name: 'Aprende' }))
      .toHaveAttribute('aria-controls', 'public-navigation-learn-panel');
    expect(within(editorialNavigation).getByRole('button', { name: 'Club' }))
      .toHaveAttribute('aria-controls', 'public-navigation-club-panel');
    expect(within(accountArea).getByRole('link', { name: 'Iniciar sesión' }))
      .toHaveAttribute('href', '/login');
    expect(within(editorialNavigation).queryByRole('link', { name: 'Club' })).not.toBeInTheDocument();
    expect(within(editorialNavigation).queryByRole('link', { name: 'Aprende' })).not.toBeInTheDocument();
    expect(within(editorialNavigation).queryByText(/Aviso legal|Privacidad|Cookies/))
      .not.toBeInTheDocument();
  });

  it('opens each disclosure with its canonical children and keeps only one open', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    const learnButton = screen.getByRole('button', { name: 'Aprende' });
    const clubButton = screen.getByRole('button', { name: 'Club' });

    await user.click(learnButton);
    expect(learnButton).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('link', { name: 'Aprende a jugar' }))
      .toHaveAttribute('href', '/aprende-a-jugar');
    expect(screen.getByRole('link', { name: 'Manual y reglas' }))
      .toHaveAttribute('href', '/aprende-a-jugar/manual');
    expect(screen.getByRole('link', { name: 'Escuela de Galotxas' }))
      .toHaveAttribute('href', '/escuela');

    await user.click(clubButton);
    expect(learnButton).toHaveAttribute('aria-expanded', 'false');
    expect(clubButton).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('link', { name: 'Quiénes somos' }))
      .toHaveAttribute('href', '/club/quienes-somos');
    expect(screen.getByRole('link', { name: 'Contacto' }))
      .toHaveAttribute('href', '/club/contacto');
    expect(screen.getByRole('link', { name: 'Federarse' }))
      .toHaveAttribute('href', '/club/federarse');
    expect(screen.getByRole('link', { name: 'Documentos' }))
      .toHaveAttribute('href', '/club/documentos');
  });

  it('supports native keyboard activation for disclosure buttons', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });
    const learnButton = screen.getByRole('button', { name: 'Aprende' });

    learnButton.focus();
    await user.keyboard('{Enter}');
    expect(learnButton).toHaveAttribute('aria-expanded', 'true');
    await user.keyboard(' ');
    expect(learnButton).toHaveAttribute('aria-expanded', 'false');
  });

  it('closes a disclosure with Escape and restores focus to its trigger', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });
    const learnButton = screen.getByRole('button', { name: 'Aprende' });

    await user.click(learnButton);
    screen.getByRole('link', { name: 'Manual y reglas' }).focus();
    await user.keyboard('{Escape}');

    expect(learnButton).toHaveAttribute('aria-expanded', 'false');
    expect(learnButton).toHaveFocus();
  });

  it('closes disclosures when clicking outside the header', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <>
        <Navbar />
        <button type="button">Fuera</button>
      </>,
      { authValue: anonymousAuth },
    );

    await user.click(screen.getByRole('button', { name: 'Club' }));
    await user.click(screen.getByRole('button', { name: 'Fuera' }));

    expect(screen.getByRole('button', { name: 'Club' })).toHaveAttribute('aria-expanded', 'false');
  });

  it('closes every menu after selecting a nested destination', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    await openMainMenu(user);
    await user.click(screen.getByRole('button', { name: 'Aprende' }));
    await user.click(screen.getByRole('link', { name: 'Escuela de Galotxas' }));

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('button', { name: 'Aprende' }))
      .toHaveAttribute('aria-expanded', 'false');
  });

  it('closing the main menu also closes its open disclosure', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    await openMainMenu(user);
    await user.click(screen.getByRole('button', { name: 'Club' }));
    await user.click(screen.getByRole('button', { name: 'Cerrar menú de navegación' }));
    await openMainMenu(user);

    expect(screen.getByRole('button', { name: 'Club' })).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('link', { name: 'Quiénes somos', hidden: true })).not.toBeVisible();
  });

  it('Escape closes a disclosure before the main menu and restores each trigger focus', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Navbar />, { authValue: anonymousAuth });

    await openMainMenu(user);
    const learnButton = screen.getByRole('button', { name: 'Aprende' });
    await user.click(learnButton);
    await user.keyboard('{Escape}');
    expect(learnButton).toHaveFocus();
    expect(screen.getByRole('button', { name: 'Cerrar menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'true');

    await user.keyboard('{Escape}');
    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' })).toHaveFocus();
  });

  it('closes the menu when the location changes programmatically', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <>
        <Navbar />
        <LocationChangeControl />
      </>,
      { authValue: anonymousAuth },
    );

    await openMainMenu(user);
    await user.click(screen.getByRole('button', { name: 'Aprende' }));
    await user.click(screen.getByRole('button', { name: 'Cambiar ubicación' }));

    expect(screen.getByRole('button', { name: 'Abrir menú de navegación' }))
      .toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('button', { name: 'Aprende' })).toHaveAttribute('aria-expanded', 'false');
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

    expect(within(editorialNavigation).queryByRole('link', { name: 'Mi Panel' }))
      .not.toBeInTheDocument();
    expect(within(accountArea).getByText(/Hola,/)).toHaveTextContent('Robert');
    expect(within(accountArea).getByRole('link', { name: 'Mi Panel' }))
      .toHaveAttribute('href', '/player');
    expect(within(accountArea).queryByRole('link', { name: 'Iniciar sesión' }))
      .not.toBeInTheDocument();

    await user.click(within(accountArea).getByRole('button', { name: 'Salir' }));
    expect(logout).toHaveBeenCalledOnce();
  });

  it.each([
    ['/', 'Inicio', 'link', 'page'],
    ['/competicion', 'Competición', 'link', 'page'],
    ['/torneos/12', 'Competición', 'link', null],
    ['/categories/8/standings', 'Competición', 'link', null],
    ['/aprende-a-jugar', 'Aprende', 'button', null],
    ['/aprende-a-jugar/manual/reglamento/el-saque', 'Aprende', 'button', null],
    ['/escuela', 'Aprende', 'button', null],
    ['/club/quienes-somos', 'Club', 'button', null],
    ['/club/contacto', 'Club', 'button', null],
  ])('marks the correct first-level branch at %s', (route, name, role, current) => {
    renderWithProviders(<Navbar />, { route, authValue: anonymousAuth });
    const activeControl = screen.getByRole(role, { name });

    expect(activeControl).toHaveClass(styles.navItemActive);
    if (current) {
      expect(activeControl).toHaveAttribute('aria-current', current);
    } else {
      expect(activeControl).not.toHaveAttribute('aria-current');
    }
  });

  it('sets aria-current page only on an exact child destination', () => {
    renderWithProviders(<Navbar />, {
      route: '/aprende-a-jugar/manual',
      authValue: anonymousAuth,
    });

    expect(screen.getByRole('link', { name: 'Manual y reglas', hidden: true }))
      .toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('button', { name: 'Aprende' })).not.toHaveAttribute('aria-current');
  });

  it('does not activate public navigation on CMS legacy or account routes', () => {
    const { rerender } = renderWithProviders(<Navbar />, {
      route: '/contenidos/nosotros',
      authValue: anonymousAuth,
    });
    const editorialNavigation = screen.getByRole('list', { name: 'Navegación editorial' });

    expect(within(editorialNavigation).queryAllByRole('link', { current: true, hidden: true }))
      .toHaveLength(0);
    expect(within(editorialNavigation).getAllByRole('link', { hidden: true }).every(
      (link) => !link.classList.contains(styles.navItemActive),
    )).toBe(true);

    rerender(<div />);
  });
});
