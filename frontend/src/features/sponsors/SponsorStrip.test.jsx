import { render, screen } from '@testing-library/react';
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
    logo: { url: 'https://api.test/sponsors/2/logo', width: 800, height: 400 },
    website_url: 'https://second.example.com',
  },
  {
    id: 1,
    name: 'Primer colaborador',
    logo: { url: 'https://api.test/sponsors/1/logo', width: 500, height: 250 },
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

    const link = screen.getByRole('link', { name: /Segundo colaborador.*pestaña nueva/ });
    expect(link).toHaveAttribute('href', 'https://second.example.com');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'sponsored noopener noreferrer');
    expect(screen.queryByRole('link', { name: /Primer colaborador/ })).not.toBeInTheDocument();
  });

  it.each(['loading', 'empty', 'error'])('renders nothing in %s state', (status) => {
    useSponsors.mockReturnValue({ sponsors: [], status });

    const { container } = render(<SponsorStrip />);

    expect(container).toBeEmptyDOMElement();
  });
});
