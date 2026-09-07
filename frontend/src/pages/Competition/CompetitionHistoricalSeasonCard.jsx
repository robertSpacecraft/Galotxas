import { Link } from 'react-router-dom';
import { CompetitionCoverImage } from '../../components/Competition/CompetitionCoverImage';
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
    <div className={styles.historicalContent}>
      <span className={styles.historicalStatus}>{getSeasonStatusLabel(season?.status)}</span>
      <h3 className={styles.historicalTitle}>{season?.name || 'Temporada sin nombre'}</h3>
      {datesLabel ? <span className={styles.historicalDates}>{datesLabel}</span> : null}
    </div>
  );

  return (
    <li className={styles.historicalItem}>
      {seasonPath ? (
        <Link
          to={seasonPath}
          className={styles.historicalLink}
          aria-label={`Ver campeonatos de ${season?.name || 'la temporada'}`}
        >
          <CompetitionCoverImage image={season?.image} className={styles.historicalCover} />
          {content}
        </Link>
      ) : (
        <article className={styles.historicalLink}>
          <CompetitionCoverImage image={season?.image} className={styles.historicalCover} />
          {content}
        </article>
      )}
    </li>
  );
};
