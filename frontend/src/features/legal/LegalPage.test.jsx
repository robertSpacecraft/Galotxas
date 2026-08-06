import { screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { LegalPage } from './LegalPage';

const originalTitle = document.title;

afterEach(() => {
  document.title = originalTitle;
  document.head.querySelector('meta[name="description"]')?.remove();
});

describe('LegalPage', () => {
  it.each([
    ['LEG-001', '/legal/aviso-legal', 'Aviso legal', 'Objeto del sitio', '1.0.0'],
    ['LEG-002', '/legal/privacidad', 'Política de privacidad', 'Conservación', '1.1.0'],
    ['LEG-003', '/legal/cookies', 'Política de cookies y almacenamiento local', 'Web pública', '1.0.0'],
  ])('renders the approved projection for %s', (pageId, route, title, section, version) => {
    renderWithProviders(<LegalPage pageId={pageId} />, { route });

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: title, level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: section })).toBeInTheDocument();
    expect(screen.getByText(version)).toBeInTheDocument();
    expect(screen.getByText('06/08/2026')).toHaveAttribute('datetime', '2026-08-06');

    const navigation = screen.getByRole('navigation', { name: 'Información legal' });
    expect(within(navigation).getAllByRole('link')).toHaveLength(3);
    expect(within(navigation).getByRole('link', {
      name: pageId === 'LEG-001' ? 'Aviso legal' : pageId === 'LEG-002' ? 'Privacidad' : 'Cookies',
    })).toHaveAttribute('aria-current', 'page');
  });

  it('applies title and description metadata from the projection', async () => {
    renderWithProviders(<LegalPage pageId="LEG-001" />, { route: '/legal/aviso-legal' });

    await waitFor(() => expect(document.title).toBe('Aviso legal | Galotxas'));
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute(
      'content',
      'Identificación del titular, condiciones de uso y responsabilidades del sitio web de Galotxas.',
    );
    expect(document.head.querySelector('link[rel="canonical"]')).not.toBeInTheDocument();
  });

  it('renders legal tables responsively and external links safely', () => {
    renderWithProviders(<LegalPage pageId="LEG-002" />, { route: '/legal/privacidad' });

    expect(screen.getAllByRole('region', { name: 'Tabla legal con desplazamiento horizontal' }))
      .toHaveLength(2);
    const aepd = screen.getByRole('link', {
      name: /Agencia Española de Protección de Datos.*se abre en una pestaña nueva/,
    });
    expect(aepd).toHaveAttribute('href', 'https://www.aepd.es/');
    expect(aepd).toHaveAttribute('target', '_blank');
    expect(aepd).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('uses the accessible 404 for an invalid document and never performs API requests', () => {
    const originalFetch = globalThis.fetch;
    let fetchCalls = 0;
    globalThis.fetch = (...args) => {
      fetchCalls += 1;
      return originalFetch?.(...args);
    };

    renderWithProviders(<LegalPage pageId="LEG-999" />, { route: '/legal/desconocido' });
    expect(screen.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeInTheDocument();
    expect(fetchCalls).toBe(0);

    globalThis.fetch = originalFetch;
  });

  it('does not expose phone-shaped data or internal draft markers', () => {
    const { container } = renderWithProviders(
      <LegalPage pageId="LEG-002" />,
      { route: '/legal/privacidad' },
    );
    expect(container.textContent).not.toMatch(
      /(?<!\d)(?:(?:\+34|0034)[ .-]?)?(?:[6789]\d{8}|[6789]\d{2}[ .-]\d{3}[ .-]\d{3})(?!\d)/,
    );
    expect(container).not.toHaveTextContent(/BORRADOR|PENDIENTE DE CONFIRMACIÓN|NO PUBLICAR/i);
  });
});
