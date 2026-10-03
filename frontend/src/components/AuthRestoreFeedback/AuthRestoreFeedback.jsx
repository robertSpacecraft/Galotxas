import { RouteLoading } from '../RouteLoading/RouteLoading';
import styles from './AuthRestoreFeedback.module.css';

// Estados de la restauración de sesión que no son "anónimo confirmado".
export function AuthRestoreFeedback({ status, onRetry }) {
  if (status === 'failed') {
    return (
      <div className={styles.container} role="alert">
        <p>No se ha podido comprobar tu sesión.</p>
        <button type="button" className={styles.retry} onClick={onRetry}>Reintentar</button>
      </div>
    );
  }

  return <RouteLoading label="Comprobando tu sesión" />;
}
