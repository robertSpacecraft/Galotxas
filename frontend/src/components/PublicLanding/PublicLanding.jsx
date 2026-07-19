import styles from './PublicLanding.module.css';

export const PublicLanding = ({ children }) => (
  <article className={styles.landing}>
    {children}
  </article>
);
