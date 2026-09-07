import { useState } from 'react';
import styles from './CompetitionCoverImage.module.css';

const getImageUrl = (image) => {
  if (typeof image?.url !== 'string' || image.url.trim() !== image.url || image.url === '') {
    return null;
  }

  try {
    const parsedUrl = new URL(image.url);

    if (
      !['http:', 'https:'].includes(parsedUrl.protocol)
      || parsedUrl.username !== ''
      || parsedUrl.password !== ''
      || parsedUrl.search !== ''
      || parsedUrl.hash !== ''
      || !/^\/api\/v1\/(seasons|championships|categories)\/[^/]+\/image$/.test(parsedUrl.pathname)
    ) {
      return null;
    }

    return image.url;
  } catch {
    return null;
  }
};

export const CompetitionCoverImage = ({ image, className = '', priority = false }) => {
  const [failedUrl, setFailedUrl] = useState(null);
  const imageUrl = getImageUrl(image);

  if (!imageUrl || failedUrl === imageUrl) {
    return null;
  }

  return (
    <div className={`${styles.frame} ${className}`.trim()}>
      <img
        className={styles.image}
        src={imageUrl}
        alt=""
        loading={priority ? 'eager' : 'lazy'}
        fetchPriority={priority ? 'high' : 'auto'}
        onError={() => setFailedUrl(imageUrl)}
      />
    </div>
  );
};
