import { CompetitionHistoricalSeasonCard } from './CompetitionHistoricalSeasonCard';
import styles from './CompetitionPage.module.css';

export const CompetitionHistoricalSeasonList = ({ seasons }) => (
  <ul className={styles.historicalGrid}>
    {seasons.map((season) => (
      <CompetitionHistoricalSeasonCard key={season.id} season={season} />
    ))}
  </ul>
);
