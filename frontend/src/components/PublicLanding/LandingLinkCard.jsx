import { Link } from 'react-router-dom';
import styles from './PublicLanding.module.css';

export const LandingLinkCard = ({ to, title, description }) => (
  <Link to={to} className={styles.linkCard}>
    <span className={styles.linkCardTitle}>{title}</span>
    <span className={styles.linkCardDescription}>{description}</span>
  </Link>
);
