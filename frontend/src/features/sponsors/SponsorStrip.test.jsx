import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useSponsors } from './useSponsors';
import { SponsorStrip } from './SponsorStrip';

vi.mock('./useSponsors', () => ({
  useSponsors: vi.fn(),
}));

const sponsors = [
  {
    id: 2,
    name: 'Segundo colaborador',
    logo: {
      url: 'https://api.test/api/v1/sponsors/2/logo',
      width: 800,
      height: 400,
      variants: [
        {
          url: 'https://api.test/api/v1/sponsors/2/logo/160',
          width: 160,
          height: 80,
          mime_type: 'image/webp',
        },
      ],
    },
    website_url: 'https://second.example.com',
  },
  {
    id: 1,
    name: 'Primer colaborador',
    logo: { url: 'https://api.test/api/v1/sponsors/1/logo', width: 500, height: 250 },
    website_url: null,
  },
];

describe('SponsorStrip', () => {
  beforeEach(() => {
    useSponsors.mockReset();
  });

  it('renders every sponsor in received order with accessible logos and safe links', () => {
    useSponsors.mockReturnValue({ sponsors, status: 'content' });

    render(<SponsorStrip />);

    expect(screen.getByRole('heading', { name: 'Colaboradores', level: 2 }))
      .toBeInTheDocument();
    const images = screen.getAllByRole('img');
    expect(images.map((image) => image.alt)).toEqual([
      'Segundo colaborador',
      'Primer colaborador',
    ]);
    expect(images[0]).toHaveAttribute('width', '800');
    expect(images[0]).toHaveAttribute('height', '400');
    expect(images[0]).toHaveAttribute('loading', 'lazy');
    expect(images[0]).toHaveAttribute('decoding', 'async');
    expect(images[0]).toHaveAttribute(
      'srcset',
      'https://api.test/api/v1/sponsors/2/logo/160 160w, https://api.test/api/v1/sponsors/2/logo 800w',
    );
    expect(images[0]).toHaveAttribute(
      'sizes',
      '(max-width: 320px) calc(50vw - 1.75rem), (max-width: 768px) calc(33vw - 2rem), 160px',
    );
    expect(images[1]).not.toHaveAttribute('srcset');
    expect(images[1]).not.toHaveAttribute('sizes');

    const link = screen.getByRole('link', { name: /Segundo colaborador.*pestaña nueva/ });
    expect(link).toHaveAttribute('href', 'https://second.example.com');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'sponsored noopener noreferrer');
    expect(screen.queryByRole('link', { name: /Primer colaborador/ })).not.toBeInTheDocument();
  });

  it('retries the sponsor master before rendering a text fallback', () => {
    useSponsors.mockReturnValue({ sponsors: [sponsors[0]], status: 'content' });
    render(<SponsorStrip />);

    fireEvent.error(screen.getByRole('img', { name: 'Segundo colaborador' }));
    const master = screen.getByRole('img', { name: 'Segundo colaborador' });
    expect(master).toHaveAttribute('src', sponsors[0].logo.url);
    expect(master).not.toHaveAttribute('srcset');
    expect(master).not.toHaveAttribute('sizes');

    fireEvent.error(master);
    expect(screen.queryByRole('img', { name: 'Segundo colaborador' })).not.toBeInTheDocument();
    expect(screen.getByText('Segundo colaborador')).toBeInTheDocument();
  });

  it.each(['loading', 'empty', 'error'])('renders nothing in %s state', (status) => {
    useSponsors.mockReturnValue({ sponsors: [], status });

    const { container } = render(<SponsorStrip />);

    expect(container).toBeEmptyDOMElement();
  });
});
