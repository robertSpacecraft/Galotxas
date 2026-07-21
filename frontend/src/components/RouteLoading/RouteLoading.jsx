import styles from './RouteLoading.module.css';

export const RouteLoading = ({ label = 'Cargando contenido' }) => (
  <div className={styles.container} role="status" aria-live="polite">
    <span className={styles.indicator} aria-hidden="true" />
    <span>{label}</span>
  </div>
);
