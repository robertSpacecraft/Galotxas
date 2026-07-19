import React from 'react';
import { formatCompetitionNumber } from '../../pages/Competition/competitionPresentation';
import styles from './TournamentRanking.module.css';

export const TournamentRanking = ({ ranking }) => {
  if (!ranking || ranking.length === 0) {
    return (
      <section className={styles.rankingContainer}>
        <h2 className={styles.title}>Ranking del campeonato</h2>
        <p className={styles.noData}>Todavía no hay datos de ranking para este campeonato.</p>
      </section>
    );
  }

  return (
    <section className={styles.rankingContainer}>
      <h2 className={styles.title}>Ranking del campeonato</h2>
      <div
        className={styles.tableWrapper}
        role="region"
        aria-label="Tabla del ranking del campeonato"
        tabIndex="0"
      >
        <table className={styles.rankingTable}>
          <caption className={styles.visuallyHidden}>Ranking del campeonato</caption>
          <thead>
            <tr>
              <th scope="col">Pos.</th>
              <th scope="col">Jugador</th>
              <th scope="col" className={styles.center}>PJ</th>
              <th scope="col" className={styles.center}>PG</th>
              <th scope="col" className={styles.center}>PP</th>
              <th scope="col" className={styles.center}>Puntos</th>
              <th scope="col" className={styles.center}>Puntos ponderados</th>
              <th scope="col" className={styles.center}>JF</th>
              <th scope="col" className={styles.center}>JC</th>
              <th scope="col" className={styles.center}>Dif.</th>
              <th scope="col" className={styles.hideMobile}>Categorías</th>
            </tr>
          </thead>
          <tbody>
            {ranking.map((item) => (
              <tr key={item.player_id || `${item.position}-${item.name}`}>
                <td className={styles.position}>{item.position ?? '—'}</td>
                <th scope="row" className={styles.name}>{item.name || 'Jugador no disponible'}</th>
                <td className={styles.center}>{item.played ?? '—'}</td>
                <td className={styles.center}>{item.wins ?? '—'}</td>
                <td className={styles.center}>{item.losses ?? '—'}</td>
                <td className={`${styles.center} ${styles.points}`}>
                  {formatCompetitionNumber(item.raw_points)}
                </td>
                <td className={`${styles.center} ${styles.weightedPoints}`}>
                  {formatCompetitionNumber(item.weighted_points)}
                </td>
                <td className={styles.center}>{formatCompetitionNumber(item.games_for)}</td>
                <td className={styles.center}>{formatCompetitionNumber(item.games_against)}</td>
                <td className={`${styles.center} ${styles.diff}`}>
                  {formatCompetitionNumber(item.games_diff)}
                </td>
                <td className={styles.hideMobile}>
                  <div className={styles.categoriesList}>
                    {item.categories_played_list?.join(', ') || 'Sin categorías registradas'}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
};
