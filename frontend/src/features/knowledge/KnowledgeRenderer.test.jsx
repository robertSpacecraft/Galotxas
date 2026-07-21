import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '../../test/renderWithProviders';
import { KnowledgeRenderer } from './KnowledgeRenderer';

const blocks = [
  {
    type: 'heading',
    level: 2,
    id: 'area-de-joc',
    children: [{ type: 'text', value: 'Àrea de joc' }],
  },
  {
    type: 'paragraph',
    children: [
      { type: 'text', value: 'La pilota és ' },
      { type: 'strong', children: [{ type: 'text', value: 'essencial' }] },
      { type: 'text', value: ' i ' },
      { type: 'emphasis', children: [{ type: 'text', value: 'tradicional' }] },
      { type: 'text', value: '. Consulta ' },
      {
        type: 'reference',
        targetId: 'REG-003',
        label: 'REG-003 – El saque',
        href: '/aprende-a-jugar/manual/reglamento/el-saque',
      },
      { type: 'text', value: '.' },
    ],
  },
  {
    type: 'unorderedList',
    items: [{ type: 'listItem', children: [{ type: 'text', value: 'Pilota' }] }],
  },
  {
    type: 'orderedList',
    start: 3,
    items: [{ type: 'listItem', value: 3, children: [{ type: 'text', value: 'Saque' }] }],
  },
  {
    type: 'table',
    headers: [[{ type: 'text', value: 'Puntuació' }]],
    rows: [[[{ type: 'text', value: 'Quinze' }]]],
  },
  { type: 'thematicBreak' },
  { type: 'unknown', content: '<script>alert(1)</script>' },
  { type: 'heading', level: 1, id: 'prohibido', children: [{ type: 'text', value: 'H1' }] },
];

describe('KnowledgeRenderer', () => {
  it('renderiza sólo nodos seguros mediante HTML semántico y nunca genera H1', () => {
    const { container } = renderWithProviders(<KnowledgeRenderer blocks={blocks} />);

    expect(screen.getByRole('heading', { name: 'Àrea de joc', level: 2 })).toHaveAttribute(
      'id',
      'area-de-joc',
    );
    expect(screen.getByRole('heading', { name: 'Àrea de joc', level: 2 })).toHaveAttribute(
      'tabindex',
      '-1',
    );
    expect(screen.getByText('essencial').tagName).toBe('STRONG');
    expect(screen.getByText('tradicional').tagName).toBe('EM');
    expect(screen.getByRole('link', { name: 'REG-003 – El saque' })).toHaveAttribute(
      'href',
      '/aprende-a-jugar/manual/reglamento/el-saque',
    );
    expect(screen.getAllByRole('list')).toHaveLength(2);
    expect(container.querySelector('ol')).toHaveAttribute('start', '3');
    expect(screen.getByRole('columnheader', { name: 'Puntuació' })).toHaveAttribute('scope', 'col');
    expect(screen.getByRole('cell', { name: 'Quinze' })).toBeInTheDocument();
    expect(container.querySelector('hr')).toBeInTheDocument();
    expect(container.querySelector('h1')).not.toBeInTheDocument();
    expect(container.innerHTML).not.toContain('<script>');
    expect(container.querySelector('[dangerouslySetInnerHTML]')).not.toBeInTheDocument();
  });

  it('trata entradas desconocidas o ausentes como contenido vacío seguro', () => {
    const { container } = renderWithProviders(<KnowledgeRenderer blocks={null} />);
    expect(container.textContent).toBe('');
  });
});
