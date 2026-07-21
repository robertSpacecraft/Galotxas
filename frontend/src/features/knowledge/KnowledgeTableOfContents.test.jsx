import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { KnowledgeTableOfContents } from './KnowledgeTableOfContents';

const document = {
  id: 'REG-TEST',
  slug: 'prova-valenciana',
  collection: 'reglamento',
  headings: [
    { level: 1, id: 'prohibit', text: 'No debe aparecer' },
    { level: 2, id: 'area-de-joc', text: 'Àrea de joc' },
    { level: 3, id: 'definicio', text: 'Definició' },
    { level: 4, id: 'definicio-2', text: 'Definició especial' },
  ],
};

describe('KnowledgeTableOfContents', () => {
  it('usa sólo headings compilados H2–H6, conserva orden, niveles, valenciano e IDs', () => {
    renderWithProviders(<KnowledgeTableOfContents document={document} />);

    const navigation = screen.getByRole('navigation', { name: 'En este documento' });
    const links = navigation.querySelectorAll('a');

    expect(links).toHaveLength(3);
    expect([...links].map((link) => link.textContent)).toEqual([
      'Àrea de joc',
      'Definició',
      'Definició especial',
    ]);
    expect(links[0]).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual/reglamento/prova-valenciana#area-de-joc',
    );
    expect(links[2]).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual/reglamento/prova-valenciana#definicio-2',
    );
    expect(screen.queryByText('No debe aparecer')).not.toBeInTheDocument();
  });

  it('omite el índice sin headings internos y lo mantiene para un único destino útil', () => {
    const emptyView = renderWithProviders(
      <KnowledgeTableOfContents document={{ ...document, headings: [] }} />,
    );

    expect(screen.queryByRole('navigation', { name: 'En este documento' })).not.toBeInTheDocument();
    emptyView.unmount();

    renderWithProviders(
      <KnowledgeTableOfContents
        document={{ ...document, headings: [{ level: 2, id: 'unic', text: 'Únic apartat' }] }}
      />,
    );
    expect(screen.getByRole('link', { name: 'Únic apartat' })).toBeInTheDocument();
  });
});
