import { Link } from 'react-router-dom';
import styles from './PublicLanding.module.css';

export const LandingActions = ({ label, actions }) => {
  if (!Array.isArray(actions) || actions.length === 0) {
    return null;
  }

  return (
    <nav className={styles.actions} aria-label={label}>
      {actions.map(({ to, label: actionLabel, variant = 'secondary' }) => (
        <Link
          key={`${to}-${actionLabel}`}
          to={to}
          className={`${styles.action} ${
            variant === 'primary' ? styles.actionPrimary : styles.actionSecondary
          }`}
        >
          {actionLabel}
        </Link>
      ))}
    </nav>
  );
};
