import { Link } from 'react-router-dom';
import styles from './NotFoundPage.module.css';

export const NotFoundPage = () => (
  <div className={styles.container}>
    <div className={styles.content}>
      <h1 className={styles.title}>Página no encontrada</h1>
      <p className={styles.description}>La página que buscas no está disponible.</p>
      <div className={styles.actions}>
        <Link to="/" className={styles.primaryLink}>Volver a Inicio</Link>
        <Link to="/competicion" className={styles.secondaryLink}>Ir a Competición</Link>
      </div>
    </div>
  </div>
);
