import { useResponsiveImageFallback } from '../../hooks/useResponsiveImageFallback';
import { normalizeResponsiveVariants } from '../../utils/responsiveImageContract';
import styles from './CompetitionCoverImage.module.css';

const getImage = (image) => {
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

    const variants = normalizeResponsiveVariants({
      variants: image.variants,
      masterUrl: image.url,
      masterPath: parsedUrl.pathname,
    });

    return {
      url: image.url,
      width: Number.isInteger(image.width) && image.width > 0 ? image.width : undefined,
      height: Number.isInteger(image.height) && image.height > 0 ? image.height : undefined,
      variants: variants ?? [],
    };
  } catch {
    return null;
  }
};

export const CompetitionCoverImage = ({ image, sizes, className = '', priority = false }) => {
  const resolved = getImage(image);
  const { failed, imageProps } = useResponsiveImageFallback({
    masterUrl: resolved?.url ?? '',
    masterWidth: resolved?.width,
    variants: resolved?.variants,
    sizes,
  });

  if (!resolved || failed) {
    return null;
  }

  return (
    <div className={`${styles.frame} ${className}`.trim()}>
      <img
        className={styles.image}
        {...imageProps}
        width={resolved.width}
        height={resolved.height}
        alt=""
        loading={priority ? 'eager' : 'lazy'}
        fetchPriority={priority ? 'high' : 'auto'}
      />
    </div>
  );
};
