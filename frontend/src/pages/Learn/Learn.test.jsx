import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { LearnPage } from './LearnPage';
import { ManualPage } from './ManualPage';
import { KnowledgeDocumentPage } from './KnowledgeDocumentPage';

describe('Aprende a jugar', () => {
  it('publica una landing mínima con un H1, metadata y acceso al Manual', () => {
    renderWithProviders(<LearnPage />);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Aprende a jugar', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Consultar el Manual' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual',
    );
    expect(document.title).toBe('Aprende a jugar | Galotxas');

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
    expect(screen.getAllByRole('link')).toHaveLength(40);
    expect(screen.getAllByRole('link')[0]).toHaveTextContent('Modelo de la cancha');
    expect(screen.getAllByRole('link').at(-1)).toHaveTextContent('Sentaura');
    expect(screen.queryByText('Vigente')).not.toBeInTheDocument();
    expect(screen.queryByText(/sourcePath|conceptos\/juego/)).not.toBeInTheDocument();
  });
});

describe('página reutilizable de documento', () => {
  it('renderiza un reglamento, su tabla, metadata y retorno determinista', () => {
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
    expect(screen.getAllByRole('link', { name: '← Volver al Manual' })).toHaveLength(2);
    expect(container.querySelectorAll('h1')).toHaveLength(1);
    expect(document.title).toBe('Sistema de puntuación | Manual | Galotxas');
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
