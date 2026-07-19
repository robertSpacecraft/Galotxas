import styles from './CompetitionPage.module.css';

const PREVIEW_LIMIT = 5;

const hasNumericValue = (value) => typeof value === 'number' && Number.isFinite(value);

export const CompetitionRankingPreview = ({ ranking }) => {
  const entries = Array.isArray(ranking) ? ranking.slice(0, PREVIEW_LIMIT) : [];

  return (
    <ol className={styles.rankingList} aria-label="Primeras posiciones del ranking histórico">
      {entries.map((entry) => (
        <li
          key={entry.player_id ?? `${entry.position}-${entry.name}`}
          className={styles.rankingEntry}
        >
          <div className={styles.rankingIdentity}>
            {entry.position !== null && entry.position !== undefined ? (
              <span className={styles.rankingPosition}>Posición {entry.position}</span>
            ) : (
              <span className={styles.rankingPosition}>Sin posición oficial</span>
            )}
            {entry.name ? <h3 className={styles.rankingName}>{entry.name}</h3> : null}
            {Array.isArray(entry.categories_played_list)
              && entry.categories_played_list.length > 0 ? (
                <p className={styles.rankingContext}>
                  Categorías: {entry.categories_played_list.join(', ')}
                </p>
              ) : null}
          </div>
          {hasNumericValue(entry.weighted_points) ? (
            <p className={styles.rankingPoints}>
              <strong>{entry.weighted_points}</strong> puntos ponderados
            </p>
          ) : null}
        </li>
      ))}
    </ol>
  );
};
