import { screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { Footer } from './Footer';

describe('Footer', () => {
  it('renders the official identity, current year and canonical Club links', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2031-04-20T10:00:00Z'));

    renderWithProviders(<Footer />);

    const clubNavigation = screen.getByRole('navigation', { name: 'Enlaces del Club' });
    expect(screen.getByRole('contentinfo')).toBeInTheDocument();
    expect(screen.getByText('Club Galotxes Monòver', { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByText('© 2031 Club Galotxes Monòver')).toBeInTheDocument();
    expect(within(clubNavigation).getByRole('link', { name: 'Quiénes somos' }))
      .toHaveAttribute('href', '/club/quienes-somos');
    expect(within(clubNavigation).getByRole('link', { name: 'Contacto' }))
      .toHaveAttribute('href', '/club/contacto');
    expect(within(clubNavigation).getByRole('link', { name: 'Federarse' }))
      .toHaveAttribute('href', '/club/federarse');
    expect(within(clubNavigation).getByRole('link', { name: 'Documentos' }))
      .toHaveAttribute('href', '/club/documentos');

    vi.useRealTimers();
  });

  it('opens confirmed social links safely in a new tab with an accessible indication', () => {
    renderWithProviders(<Footer />);
    const socialNavigation = screen.getByRole('navigation', { name: 'Redes sociales' });
    const facebook = within(socialNavigation).getByRole('link', {
      name: /Facebook.*se abre en una pestaña nueva/,
    });
    const instagram = within(socialNavigation).getByRole('link', {
      name: /Instagram.*se abre en una pestaña nueva/,
    });

    expect(facebook).toHaveAttribute(
      'href',
      'https://www.facebook.com/galotxes.monover?locale=es_ES',
    );
    expect(instagram).toHaveAttribute('href', 'https://www.instagram.com/clubgalotxes/');

    for (const link of [facebook, instagram]) {
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    }
  });

  it('does not publish legal, Press, Federations or unconfirmed contact data', () => {
    renderWithProviders(<Footer />);

    for (const excludedText of [
      'Privacidad',
      'Aviso legal',
      'Cookies',
      'Prensa',
      'Federaciones',
      'Accesibilidad',
    ]) {
      expect(screen.queryByText(excludedText, { exact: false })).not.toBeInTheDocument();
    }

    expect(screen.queryByRole('link', { name: /^\+?[0-9 ]+$/ })).not.toBeInTheDocument();
  });
});
