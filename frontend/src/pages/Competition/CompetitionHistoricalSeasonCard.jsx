import { Link } from 'react-router-dom';
import { getTournamentsSeasonPath } from '../../navigation/competitionRoutes';
import {
  getCompetitionDateRangeLabel,
  getSeasonStatusLabel,
} from './competitionPresentation';
import styles from './CompetitionPage.module.css';

export const CompetitionHistoricalSeasonCard = ({ season }) => {
  const seasonPath = getTournamentsSeasonPath(season?.id);
  const datesLabel = getCompetitionDateRangeLabel(season?.start_date, season?.end_date);
  const content = (
    <>
      <span className={styles.historicalStatus}>{getSeasonStatusLabel(season?.status)}</span>
      <h3 className={styles.historicalTitle}>{season?.name || 'Temporada sin nombre'}</h3>
      {datesLabel ? <span className={styles.historicalDates}>{datesLabel}</span> : null}
    </>
  );

  return (
    <li className={styles.historicalItem}>
      {seasonPath ? (
        <Link
          to={seasonPath}
          className={styles.historicalLink}
          aria-label={`Ver campeonatos de ${season?.name || 'la temporada'}`}
        >
          {content}
        </Link>
      ) : (
        <article className={styles.historicalLink}>{content}</article>
      )}
    </li>
  );
};
