import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CompetitionCoverImage } from './CompetitionCoverImage';

describe('CompetitionCoverImage', () => {
  it.each([
    null,
    {},
    { url: '' },
    { url: 'not-an-absolute-url' },
    { url: 'javascript:alert(1)' },
    { url: 'https://storage.example.test/banners/key.jpg?X-Amz-Signature=secret' },
  ])('renders no media for an absent or invalid image contract', (image) => {
    const { container } = render(<CompetitionCoverImage image={image} />);

    expect(container).toBeEmptyDOMElement();
  });

  it('uses the stable API URL directly as a lazy decorative image by default', () => {
    render(<CompetitionCoverImage image={{ url: 'https://api.example.test/api/v1/seasons/7/image' }} />);

    const image = screen.getByRole('presentation');
    expect(image).toHaveAttribute('src', 'https://api.example.test/api/v1/seasons/7/image');
    expect(image).toHaveAttribute('alt', '');
    expect(image).toHaveAttribute('loading', 'lazy');
    expect(image).toHaveAttribute('fetchpriority', 'auto');
  });

  it('supports eager priority for a primary detail cover', () => {
    render(
      <CompetitionCoverImage
        image={{ url: 'https://api.example.test/api/v1/championships/9/image' }}
        priority
      />,
    );

    const image = screen.getByRole('presentation');
    expect(image).toHaveAttribute('loading', 'eager');
    expect(image).toHaveAttribute('fetchpriority', 'high');
  });

  it('removes the whole media block after a load error', () => {
    const { container } = render(
      <CompetitionCoverImage image={{ url: 'https://api.example.test/api/v1/categories/12/image' }} />,
    );

    fireEvent.error(screen.getByRole('presentation'));

    expect(container).toBeEmptyDOMElement();
  });

  it('allows a different URL to render after the previous URL failed', () => {
    const { rerender } = render(
      <CompetitionCoverImage image={{ url: 'https://api.example.test/api/v1/categories/12/image' }} />,
    );
    fireEvent.error(screen.getByRole('presentation'));

    rerender(
      <CompetitionCoverImage image={{ url: 'https://api.example.test/api/v1/categories/13/image' }} />,
    );

    expect(screen.getByRole('presentation'))
      .toHaveAttribute('src', 'https://api.example.test/api/v1/categories/13/image');
  });
});
