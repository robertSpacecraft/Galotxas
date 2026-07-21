import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { LearnPage } from './LearnPage';
import { ManualPage } from './ManualPage';
import { KnowledgeDocumentPage } from './KnowledgeDocumentPage';

describe('Aprende a jugar', () => {
  it('publica una landing funcional con un H1, resumen derivado y acceso al Manual', () => {
    renderWithProviders(<LearnPage />);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Aprende a jugar', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Consultar el Manual' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual',
    );
    expect(document.title).toBe('Aprende a jugar | Galotxas');
    expect(screen.getByText(/40 documentos organizados en 4 colecciones/)).toBeInTheDocument();

    for (const placeholder of ['Historia', 'Escuela', 'Cursos', 'Vídeos']) {
      expect(screen.queryByText(placeholder, { exact: true })).not.toBeInTheDocument();
    }
  });

  it('muestra las cuatro colecciones y enlaza los 40 documentos en orden', () => {
    renderWithProviders(<ManualPage />);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Manual', level: 1 })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 2 }).map(({ textContent }) => textContent)).toEqual([
      'Reglamento',
      'Conceptos — Elementos',
      'Conceptos — Personas',
      'Conceptos — Juego',
    ]);
    const documentLinks = screen.getAllByRole('link').filter((link) => (
      /^\/aprende-a-jugar\/manual\/(?:reglamento|conceptos)\//.test(link.getAttribute('href'))
    ));
    expect(documentLinks).toHaveLength(40);
    expect(documentLinks[0]).toHaveTextContent('Modelo de la cancha');
    expect(documentLinks.at(-1)).toHaveTextContent('Sentaura');
    expect(screen.getByRole('navigation', { name: 'Colecciones del Manual' }).getElementsByTagName('a'))
      .toHaveLength(4);
    expect(screen.getByRole('link', { name: 'Volver a Aprende a jugar' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar',
    );
    expect(screen.queryByText('Vigente')).not.toBeInTheDocument();
    expect(screen.queryByText(/sourcePath|conceptos\/juego/)).not.toBeInTheDocument();
  });
});

describe('página reutilizable de documento', () => {
  it('renderiza un reglamento, tabla, contexto, índice y navegación canónica', () => {
    const { container } = renderWithProviders(
      <KnowledgeDocumentPage type="regulation" />,
      {
        route: '/aprende-a-jugar/manual/reglamento/sistema-de-puntuacion',
        routePath: '/aprende-a-jugar/manual/reglamento/:slug',
      },
    );

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Sistema de puntuación', level: 1 }))
      .toBeInTheDocument();
    expect(screen.getByText('REG-006')).toBeInTheDocument();
    expect(screen.getByText('0.1.0')).toBeInTheDocument();
    expect(screen.getByText('20/07/2026')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Puntuación' })).toBeInTheDocument();
    const contextNavigation = screen.getByRole('navigation', { name: 'Contexto del Manual' });
    expect(contextNavigation).toHaveTextContent('Aprende a jugar');
    expect(contextNavigation).toHaveTextContent('Manual');
    expect(contextNavigation).toHaveTextContent('Reglamento');
    expect(screen.getByRole('navigation', { name: 'En este documento' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Anterior: Pérdida del quince' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual/reglamento/perdida-del-quince',
    );
    expect(screen.getByRole('link', { name: 'Siguiente: Modalidad por parejas' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual/reglamento/modalidad-por-parejas',
    );
    expect(screen.getByText('Documento 6 de 8 en Reglamento')).toBeInTheDocument();
    expect(screen.queryByText('← Volver al Manual')).not.toBeInTheDocument();
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(document.title).toBe('Sistema de puntuación | Manual | Galotxas');
  });

  it('navega a un fragmento compilado y enfoca el heading sólo tras activar el índice', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <KnowledgeDocumentPage type="regulation" />,
      {
        route: '/aprende-a-jugar/manual/reglamento/saque',
        routePath: '/aprende-a-jugar/manual/reglamento/:slug',
      },
    );

    const target = screen.getByRole('heading', { name: '2. Objetivo del saque', level: 2 });
    expect(target).not.toHaveFocus();

    await user.click(screen.getByRole('link', { name: '2. Objetivo del saque' }));
    await waitFor(() => expect(target).toHaveFocus());
    expect(target).toHaveAttribute('id', '2-objetivo-del-saque');
    expect(target).toHaveAttribute('tabindex', '-1');
    expect(document.title).toBe('El saque | Manual | Galotxas');
  });

  it('resuelve un deep link directo sin mover el foco de forma inesperada', async () => {
    renderWithProviders(
      <KnowledgeDocumentPage type="regulation" />,
      {
        route: '/aprende-a-jugar/manual/reglamento/saque#2-objetivo-del-saque',
        routePath: '/aprende-a-jugar/manual/reglamento/:slug',
      },
    );

    const target = screen.getByRole('heading', { name: '2. Objetivo del saque', level: 2 });
    await waitFor(() => expect(target).toBeInTheDocument());
    expect(target).not.toHaveFocus();
  });

  it('respeta los límites primero y último sin wrap', () => {
    const first = renderWithProviders(
      <KnowledgeDocumentPage type="regulation" />,
      {
        route: '/aprende-a-jugar/manual/reglamento/modelo-de-la-cancha',
        routePath: '/aprende-a-jugar/manual/reglamento/:slug',
      },
    );

    expect(screen.queryByText('Anterior', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Siguiente: Reglamento' })).toBeInTheDocument();
    first.unmount();

    renderWithProviders(
      <KnowledgeDocumentPage type="regulation" />,
      {
        route: '/aprende-a-jugar/manual/reglamento/casos-especiales',
        routePath: '/aprende-a-jugar/manual/reglamento/:slug',
      },
    );
    expect(screen.getByRole('link', { name: 'Anterior: Modalidad por parejas' })).toBeInTheDocument();
    expect(screen.queryByText('Siguiente', { exact: true })).not.toBeInTheDocument();
  });

  it('renderiza conceptos de cada grupo y conserva caracteres valencianos', () => {
    for (const [group, slug, title] of [
      ['elementos', 'pilota', 'Pilota'],
      ['personas', 'jugador', 'Jugador'],
      ['juego', 'saque', 'Saque'],
    ]) {
      const view = renderWithProviders(
        <KnowledgeDocumentPage type="concept" />,
        {
          route: `/aprende-a-jugar/manual/conceptos/${group}/${slug}`,
          routePath: '/aprende-a-jugar/manual/conceptos/:group/:slug',
        },
      );

      expect(screen.getByRole('heading', { name: title, level: 1 })).toBeInTheDocument();
      view.unmount();
    }
  });

  it.each([
    ['/aprende-a-jugar/manual/reglamento/inexistente', '/aprende-a-jugar/manual/reglamento/:slug', 'regulation'],
    ['/aprende-a-jugar/manual/conceptos/instalaciones/cancha', '/aprende-a-jugar/manual/conceptos/:group/:slug', 'concept'],
    ['/aprende-a-jugar/manual/conceptos/juego/inexistente', '/aprende-a-jugar/manual/conceptos/:group/:slug', 'concept'],
  ])('usa la 404 existente para %s sin revelar datos privados', (route, routePath, type) => {
    renderWithProviders(<KnowledgeDocumentPage type={type} />, { route, routePath });

    expect(screen.getByRole('heading', { name: 'Página no encontrada', level: 1 }))
      .toBeInTheDocument();
    expect(document.head.querySelector('meta[name="robots"]')).toHaveAttribute('content', 'noindex');
    expect(screen.queryByText(/Borrador|sourcePath/)).not.toBeInTheDocument();
  });
});
