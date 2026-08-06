import { Link } from 'react-router-dom';
import {
  publicFooterNavigation,
  publicLegalNavigation,
  publicSiteIdentity,
  publicSocialLinks,
} from '../../navigation/publicNavigation';
import styles from './Footer.module.css';

export const Footer = () => {
  const currentYear = new Date().getFullYear();

  return (
    <footer className={styles.footer}>
      <div className={styles.content}>
        <div className={styles.identity}>
          <p className={styles.name}>{publicSiteIdentity.name}</p>
          <p className={styles.copyright}>
            © {currentYear} {publicSiteIdentity.name}
          </p>
        </div>

        <nav className={styles.linkGroup} aria-label="Enlaces del Club">
          <p className={styles.groupTitle}>Club</p>
          <ul className={styles.linkList}>
            {publicFooterNavigation.map((item) => (
              <li key={item.id}>
                <Link to={item.to}>{item.label}</Link>
              </li>
            ))}
          </ul>
        </nav>

        <nav className={styles.linkGroup} aria-label="Redes sociales">
          <p className={styles.groupTitle}>Redes sociales</p>
          <ul className={styles.linkList}>
            {publicSocialLinks.map((item) => (
              <li key={item.id}>
                <a
                  href={item.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={`${item.label} (se abre en una pestaña nueva)`}
                >
                  {item.label}
                  <span className={styles.visuallyHidden} aria-hidden="true">
                    {' '}(se abre en una pestaña nueva)
                  </span>
                </a>
              </li>
            ))}
          </ul>
        </nav>

        <nav className={styles.linkGroup} aria-label="Información legal">
          <p className={styles.groupTitle}>Legal</p>
          <ul className={styles.linkList}>
            {publicLegalNavigation.map((item) => (
              <li key={item.id}>
                <Link to={item.to}>{item.label}</Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </footer>
  );
};
