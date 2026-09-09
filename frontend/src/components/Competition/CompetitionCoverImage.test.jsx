import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CompetitionCoverImage } from './CompetitionCoverImage';
import styles from './CompetitionCoverImage.module.css';

describe('CompetitionCoverImage', () => {
  const responsiveImage = {
    url: 'https://api.example.test/api/v1/categories/12/image',
    width: 1600,
    height: 900,
    variants: [
      {
        url: 'https://api.example.test/api/v1/categories/12/image/320',
        width: 320,
        height: 180,
        mime_type: 'image/webp',
      },
      {
        url: 'https://api.example.test/api/v1/categories/12/image/640',
        width: 640,
        height: 360,
        mime_type: 'image/webp',
      },
    ],
  };

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

  it('renders validated variants, contextual sizes and intrinsic dimensions', () => {
    const sizes = '(max-width: 720px) calc(100vw - 2rem), 720px';
    render(<CompetitionCoverImage image={responsiveImage} sizes={sizes} />);

    const image = screen.getByRole('presentation');
    expect(image).toHaveAttribute('src', responsiveImage.url);
    expect(image).toHaveAttribute(
      'srcset',
      `${responsiveImage.variants[0].url} 320w, ${responsiveImage.variants[1].url} 640w, ${responsiveImage.url} 1600w`,
    );
    expect(image).toHaveAttribute('sizes', sizes);
    expect(image).toHaveAttribute('width', '1600');
    expect(image).toHaveAttribute('height', '900');
  });

  it('retries the master explicitly after a responsive error, then removes the block', () => {
    const sizes = '(max-width: 720px) 100vw, 720px';
    const { container } = render(
      <CompetitionCoverImage image={responsiveImage} sizes={sizes} />,
    );

    fireEvent.error(screen.getByRole('presentation'));
    const master = screen.getByRole('presentation');
    expect(master).toHaveAttribute('src', responsiveImage.url);
    expect(master).not.toHaveAttribute('srcset');
    expect(master).not.toHaveAttribute('sizes');

    fireEvent.error(master);
    expect(container).toBeEmptyDOMElement();
    expect(screen.queryByRole('presentation')).not.toBeInTheDocument();
  });

  it('keeps a stable frame for a legacy image without dimensions before and after load', () => {
    const { container } = render(
      <CompetitionCoverImage
        image={{ url: 'https://api.example.test/api/v1/seasons/7/image' }}
      />,
    );

    const frameBeforeLoad = container.firstElementChild;
    expect(frameBeforeLoad).toBeInTheDocument();
    expect(frameBeforeLoad).toHaveClass(styles.frame);
    const image = screen.getByRole('presentation');
    expect(image).not.toHaveAttribute('width');
    expect(image).not.toHaveAttribute('height');

    fireEvent.load(image);
    expect(container.firstElementChild).toBe(frameBeforeLoad);
    expect(container.firstElementChild).toHaveClass(styles.frame);
  });

  it('degrades an invalid responsive extension to the stable master contract', () => {
    render(<CompetitionCoverImage image={{
      ...responsiveImage,
      variants: [{ ...responsiveImage.variants[0], url: `${responsiveImage.url}/999` }],
    }} sizes="100vw" />);

    const image = screen.getByRole('presentation');
    expect(image).toHaveAttribute('src', responsiveImage.url);
    expect(image).not.toHaveAttribute('srcset');
    expect(image).not.toHaveAttribute('sizes');
  });

  it('removes the whole media block after a load error', () => {
    const { container } = render(
      <CompetitionCoverImage image={{ url: 'https://api.example.test/api/v1/categories/12/image' }} />,
    );

    fireEvent.error(screen.getByRole('presentation'));

    expect(container).toBeEmptyDOMElement();
  });

  it('resets responsive failure state when the image contract identity changes', () => {
    const { rerender } = render(
      <CompetitionCoverImage image={responsiveImage} sizes="100vw" />,
    );
    const failedMaster = screen.getByRole('presentation');
    Object.defineProperty(failedMaster, 'currentSrc', {
      configurable: true,
      value: responsiveImage.url,
    });
    fireEvent.error(failedMaster);

    const nextImage = {
      ...responsiveImage,
      url: 'https://api.example.test/api/v1/categories/13/image',
      variants: responsiveImage.variants.map((variant) => ({
        ...variant,
        url: `https://api.example.test/api/v1/categories/13/image/${variant.width}`,
      })),
    };

    rerender(
      <CompetitionCoverImage image={nextImage} sizes="100vw" />,
    );

    expect(screen.getByRole('presentation')).toHaveAttribute('src', nextImage.url);
    expect(screen.getByRole('presentation')).toHaveAttribute(
      'srcset',
      `${nextImage.variants[0].url} 320w, ${nextImage.variants[1].url} 640w, ${nextImage.url} 1600w`,
    );
  });
});
