import { Link } from 'react-router-dom';
import { TOURNAMENTS_PATH } from '../../navigation/competitionRoutes';
import styles from './CompetitionPage.module.css';

export const CompetitionIndexLink = ({ children = 'Ver todos los campeonatos' }) => (
  <div className={styles.sectionActionRow}>
    <Link to={TOURNAMENTS_PATH} className={styles.secondaryLink}>{children}</Link>
  </div>
);
