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
          <CompetitionCoverImage
            image={season?.image}
            sizes="(max-width: 480px) calc(100vw - 2rem), (max-width: 800px) calc(50vw - 2rem), min(29vw, 384px)"
            className={styles.historicalCover}
          />
          {content}
        </Link>
      ) : (
        <article className={styles.historicalLink}>
          <CompetitionCoverImage
            image={season?.image}
            sizes="(max-width: 480px) calc(100vw - 2rem), (max-width: 800px) calc(50vw - 2rem), min(29vw, 384px)"
            className={styles.historicalCover}
          />
          {content}
        </article>
      )}
    </li>
  );
};
