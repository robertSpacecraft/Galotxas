import styles from './SponsorStrip.module.css';

export const SponsorLogo = ({ sponsor }) => {
  const image = (
    <img
      className={styles.logo}
      src={sponsor.logo.url}
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
