import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { NewsImage } from './NewsImage';

const imageContract = {
  url: 'https://api.example.test/api/v1/news/cronica-final/image',
  width: 1600,
  height: 900,
  alt: 'Pelota sobre la pista.',
  credit: null,
  variants: [
    {
      url: 'https://api.example.test/api/v1/news/cronica-final/image/320',
      width: 320,
      height: 180,
      mime_type: 'image/webp',
    },
    {
      url: 'https://api.example.test/api/v1/news/cronica-final/image/640',
      width: 640,
      height: 360,
      mime_type: 'image/webp',
    },
  ],
};

describe('NewsImage', () => {
  it('renders responsive candidates, contextual sizes and master dimensions', () => {
    const sizes = '(max-width: 720px) calc(100vw - 2rem), 52vw';
    render(<NewsImage image={imageContract} sizes={sizes} />);

    const image = screen.getByRole('img', { name: imageContract.alt });
    expect(image).toHaveAttribute('src', imageContract.url);
    expect(image).toHaveAttribute(
      'srcset',
      `${imageContract.variants[0].url} 320w, ${imageContract.variants[1].url} 640w, ${imageContract.url} 1600w`,
    );
    expect(image).toHaveAttribute('sizes', sizes);
    expect(image).toHaveAttribute('width', '1600');
    expect(image).toHaveAttribute('height', '900');
  });

  it('removes responsive selection, retries master and then shows the existing fallback once', () => {
    render(<NewsImage image={imageContract} sizes="100vw" />);

    const selectedVariant = screen.getByRole('img', { name: imageContract.alt });
    Object.defineProperty(selectedVariant, 'currentSrc', {
      configurable: true,
      value: imageContract.variants[0].url,
    });
    fireEvent.error(selectedVariant);
    const master = screen.getByRole('img', { name: imageContract.alt });
    expect(master).toHaveAttribute('src', imageContract.url);
    expect(master).not.toHaveAttribute('srcset');
    expect(master).not.toHaveAttribute('sizes');

    fireEvent.error(master);
    expect(screen.queryByRole('img', { name: imageContract.alt })).not.toBeInTheDocument();
    expect(screen.getByRole('img', { name: /Pelota sobre la pista.*no disponible/i }))
      .toHaveTextContent('Imagen no disponible');
  });

  it('goes directly to the visual fallback when the master candidate selected by srcset fails', () => {
    render(<NewsImage image={imageContract} sizes="100vw" />);

    const selectedMaster = screen.getByRole('img', { name: imageContract.alt });
    Object.defineProperty(selectedMaster, 'currentSrc', {
      configurable: true,
      value: imageContract.url,
    });
    fireEvent.error(selectedMaster);

    expect(screen.queryByRole('img', { name: imageContract.alt })).not.toBeInTheDocument();
    expect(screen.getByRole('img', { name: /Pelota sobre la pista.*no disponible/i }))
      .toHaveTextContent('Imagen no disponible');
  });

  it('uses only the master for a legacy contract without variants', () => {
    render(<NewsImage image={{ ...imageContract, variants: undefined }} sizes="100vw" />);

    const image = screen.getByRole('img', { name: imageContract.alt });
    expect(image).toHaveAttribute('src', imageContract.url);
    expect(image).not.toHaveAttribute('srcset');
    expect(image).not.toHaveAttribute('sizes');
  });

  it('uses the master once when its width duplicates the final derivative width', () => {
    const duplicateWidthContract = {
      ...imageContract,
      variants: [
        ...imageContract.variants,
        {
          url: `${imageContract.url}/1600`,
          width: 1600,
          height: 900,
          mime_type: 'image/webp',
        },
      ],
    };
    render(<NewsImage image={duplicateWidthContract} sizes="100vw" />);

    expect(screen.getByRole('img', { name: imageContract.alt })).toHaveAttribute(
      'srcset',
      `${imageContract.variants[0].url} 320w, ${imageContract.variants[1].url} 640w, ${imageContract.url} 1600w`,
    );
  });
});
