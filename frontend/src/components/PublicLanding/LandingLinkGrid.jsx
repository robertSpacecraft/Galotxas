import styles from './PublicLanding.module.css';

export const LandingLinkGrid = ({ label, children }) => (
  <nav className={styles.linkGrid} aria-label={label}>
    {children}
  </nav>
);
