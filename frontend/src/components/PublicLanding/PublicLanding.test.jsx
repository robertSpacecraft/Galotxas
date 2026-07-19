import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { LandingActions } from './LandingActions';
import { LandingHeader } from './LandingHeader';
import { LandingLinkCard } from './LandingLinkCard';
import { LandingLinkGrid } from './LandingLinkGrid';
import { LandingSection } from './LandingSection';
import { PublicLanding } from './PublicLanding';

describe('PublicLanding', () => {
  it('renders an article with child content and no implicit main or headings', () => {
    const { container } = renderWithProviders(
      <PublicLanding><p>Contenido de prueba</p></PublicLanding>,
    );

    expect(screen.getByText('Contenido de prueba').closest('article')).toBeInTheDocument();
    expect(container.querySelector('main')).not.toBeInTheDocument();
    expect(container.querySelector('h1, h2, h3, h4, h5, h6')).not.toBeInTheDocument();
  });
});

describe('LandingHeader', () => {
  it('associates one h1 and its introduction without rendering absent actions', () => {
    renderWithProviders(
      <LandingHeader
        id="test-header"
        title="Título de prueba"
        introduction="Introducción de prueba"
      />,
    );

    const heading = screen.getByRole('heading', { name: 'Título de prueba', level: 1 });
    const header = heading.closest('header');

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(header).toHaveAttribute('aria-labelledby', 'test-header-title');
    expect(header).toHaveAttribute('aria-describedby', 'test-header-introduction');
    expect(screen.getByText('Introducción de prueba')).toHaveAttribute(
      'id',
      'test-header-introduction',
    );
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
  });

  it('accepts an optional group of real route actions', () => {
    renderWithProviders(
      <LandingHeader
        id="action-header"
        title="Título con acciones"
        introduction="Introducción"
        actions={(
          <LandingActions
            label="Acciones de prueba"
            actions={[{ to: '/destino', label: 'Abrir destino', variant: 'primary' }]}
          />
        )}
      />,
    );

    expect(screen.getByRole('navigation', { name: 'Acciones de prueba' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Abrir destino' })).toHaveAttribute(
      'href',
      '/destino',
    );
  });
});

describe('LandingSection', () => {
  it('uses a stable explicit id and labels its section with an h2', () => {
    renderWithProviders(
      <LandingSection
        id="test-section"
        title="Sección de prueba"
        introduction="Contexto de la sección"
      >
        <button type="button">Acción hija</button>
      </LandingSection>,
    );

    const section = screen.getByRole('region', { name: 'Sección de prueba' });

    expect(section).toHaveAttribute('id', 'test-section');
    expect(section).toHaveAttribute('aria-labelledby', 'test-section-title');
    expect(section).toHaveAttribute('aria-describedby', 'test-section-introduction');
    expect(screen.getByRole('heading', { name: 'Sección de prueba', level: 2 }))
      .toHaveAttribute('id', 'test-section-title');
    expect(screen.getByRole('button', { name: 'Acción hija' })).toBeInTheDocument();
  });
});

describe('Landing destinations', () => {
  it('renders one semantic link with accessible title and description', () => {
    renderWithProviders(
      <LandingLinkGrid label="Destinos de prueba">
        <LandingLinkCard
          to="/destino"
          title="Destino"
          description="Descripción accesible del destino."
        />
      </LandingLinkGrid>,
    );

    const link = screen.getByRole('link', { name: /Destino.*Descripción accesible/s });

    expect(screen.getByRole('navigation', { name: 'Destinos de prueba' })).toContainElement(link);
    expect(link).toHaveAttribute('href', '/destino');
    expect(link.querySelector('a, button, input, select, textarea')).toBeNull();
  });

  it('can be reached with Tab and activated with Enter', async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route
          path="/"
          element={(
            <LandingLinkCard
              to="/destino"
              title="Destino por teclado"
              description="Activa el enlace con Enter."
            />
          )}
        />
        <Route path="/destino" element={<p>Destino alcanzado</p>} />
      </Routes>,
    );

    await user.tab();
    expect(screen.getByRole('link', { name: /Destino por teclado/ })).toHaveFocus();
    await user.keyboard('{Enter}');
    expect(screen.getByText('Destino alcanzado')).toBeInTheDocument();
  });

  it('does not render an empty actions navigation', () => {
    const { container } = renderWithProviders(
      <LandingActions label="Acciones vacías" actions={[]} />,
    );

    expect(container).toBeEmptyDOMElement();
  });
});
