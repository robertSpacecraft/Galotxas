import { useState } from 'react';
import styles from './NewsPages.module.css';

export const NewsImage = ({ image, eager = false, className = '' }) => {
  const [failedUrl, setFailedUrl] = useState(null);
  const failed = failedUrl === image.url;

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
      src={image.url}
      width={image.width}
      height={image.height}
      alt={image.alt}
      loading={eager ? 'eager' : 'lazy'}
      fetchPriority={eager ? 'high' : 'auto'}
      onError={() => setFailedUrl(image.url)}
    />
  );
};
