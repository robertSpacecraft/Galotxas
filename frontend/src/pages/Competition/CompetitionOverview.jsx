import { CompetitionSeason } from './CompetitionSeason';
import styles from './CompetitionPage.module.css';

export const CompetitionOverview = ({ seasons }) => (
  <div className={styles.seasonList}>
    {seasons.map((season) => (
      <CompetitionSeason key={season.id} season={season} />
    ))}
  </div>
);
