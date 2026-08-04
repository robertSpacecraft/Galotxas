import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CmsBlockRenderer } from './CmsBlockRenderer';

describe('CmsBlockRenderer link protocols', () => {
  it('renders a validated mailto link without opening a new context', () => {
    render(<CmsBlockRenderer block={{
      type: 'button',
      data: {
        label: 'Escribir al club',
        url: 'mailto:contacto@example.com',
      },
    }} />);

    const link = screen.getByRole('link', { name: 'Escribir al club' });

    expect(link).toHaveAttribute(
      'href',
      'mailto:contacto@example.com',
    );
    expect(link).not.toHaveAttribute('target');
  });

  it.each([
    'mailto:not-an-email',
    'mailto:club@example.com?subject=Injected',
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox(1)',
    'custom:arbitrary',
  ])('does not render the unsafe protocol %s', (url) => {
    const { container } = render(<CmsBlockRenderer block={{
      type: 'document_link',
      data: { label: 'Enlace inseguro', url },
    }} />);

    expect(container).toBeEmptyDOMElement();
  });

  it('never accepts mailto as an image source', () => {
    const { container } = render(<CmsBlockRenderer block={{
      type: 'image',
      data: {
        url: 'mailto:contacto@example.com',
        alt: 'No es una imagen',
      },
    }} />);

    expect(container).toBeEmptyDOMElement();
  });
});
