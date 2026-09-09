import { useResponsiveImageFallback } from '../../hooks/useResponsiveImageFallback';
import styles from './NewsPages.module.css';

export const NewsImage = ({ image, sizes, eager = false, className = '' }) => {
  const { failed, imageProps } = useResponsiveImageFallback({
    masterUrl: image.url,
    masterWidth: image.width,
    variants: image.variants,
    sizes,
  });

  if (failed) {
    return (
      <div
        className={`${styles.imageFallback} ${className}`}
        role="img"
        aria-label={`${image.alt}. Imagen no disponible.`}
      >
        Imagen no disponible
      </div>
    );
  }

  return (
    <img
      className={className}
      {...imageProps}
      width={image.width}
      height={image.height}
      alt={image.alt}
      loading={eager ? 'eager' : 'lazy'}
      fetchPriority={eager ? 'high' : 'auto'}
    />
  );
};
