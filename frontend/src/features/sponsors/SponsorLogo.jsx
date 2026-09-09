import { useResponsiveImageFallback } from '../../hooks/useResponsiveImageFallback';
import styles from './SponsorStrip.module.css';

export const SponsorLogo = ({ sponsor }) => {
  const { failed, imageProps } = useResponsiveImageFallback({
    masterUrl: sponsor.logo.url,
    masterWidth: sponsor.logo.width,
    variants: sponsor.logo.variants,
    sizes: '(max-width: 320px) calc(50vw - 1.75rem), (max-width: 768px) calc(33vw - 2rem), 160px',
  });
  const image = (
    failed ? <span className={styles.logoFallback}>{sponsor.name}</span> : <img
      className={styles.logo}
      {...imageProps}
      alt={sponsor.name}
      width={sponsor.logo.width}
      height={sponsor.logo.height}
      loading="lazy"
      decoding="async"
    />
  );

  return (
    <li className={styles.item}>
      {sponsor.website_url ? (
        <a
          className={styles.link}
          href={sponsor.website_url}
          target="_blank"
          rel="sponsored noopener noreferrer"
          aria-label={`${sponsor.name} (sitio externo, se abre en una pestaña nueva)`}
        >
          {image}
        </a>
      ) : (
        <div className={styles.logoContainer}>{image}</div>
      )}
    </li>
  );
};
