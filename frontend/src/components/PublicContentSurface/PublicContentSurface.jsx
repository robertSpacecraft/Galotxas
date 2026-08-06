import styles from './PublicContentSurface.module.css';

export const PublicContentSurface = ({ children }) => (
  <div className={styles.surface}>
    {children}
  </div>
);
