import React from 'react';
import styles from './TournamentRanking.module.css';

export const TournamentRanking = ({ ranking }) => {
  if (!ranking || ranking.length === 0) {
    return <p className={styles.noData}>Aún no hay datos de ranking para este campeonato.</p>;
  }

  return (
    <div className={styles.rankingContainer}>
      <h2 className={styles.title}>Clasificación General del Campeonato</h2>
      <div className={styles.tableWrapper}>
        <table className={styles.rankingTable}>
          <thead>
            <tr>
              <th>Puesto</th>
              <th>Jugador</th>
              <th className={styles.center}>PJ</th>
              <th className={styles.center}>PG</th>
              <th className={styles.center}>PP</th>
              <th className={styles.center}>Puntos</th>
              <th className={styles.center}>Puntos ponderados</th>
              <th className={styles.center}>JF</th>
              <th className={styles.center}>JC</th>
              <th className={styles.center}>Dif.</th>
              <th className={styles.hideMobile}>Categorías</th>
            </tr>
          </thead>
          <tbody>
            {ranking.map((item, index) => (
              <tr key={item.player_id || index} className={index < 3 ? styles.topThree : ''}>
                <td className={styles.position}>{index + 1}</td>
                <td className={styles.name}>{item.name || item.player?.name}</td>
                <td className={styles.center}>{item.played}</td>
                <td className={styles.center}>{item.wins}</td>
                <td className={styles.center}>{item.losses}</td>
                <td className={`${styles.center} ${styles.points}`}>
                  {parseFloat(item.raw_points).toFixed(2).replace('.', ',')}
                </td>
                <td className={`${styles.center} ${styles.weightedPoints}`}>
                  {parseFloat(item.weighted_points).toFixed(2).replace('.', ',')}
                </td>
                <td className={styles.center}>{parseFloat(item.games_for).toFixed(2).replace('.', ',')}</td>
                <td className={styles.center}>{parseFloat(item.games_against).toFixed(2).replace('.', ',')}</td>
                <td className={`${styles.center} ${styles.diff}`}>
                  {parseFloat(item.games_diff).toFixed(2).replace('.', ',')}
                </td>
                <td className={styles.hideMobile}>
                  <div className={styles.categoriesList}>
                    {item.categories_played_list?.join(', ') || 'N/A'}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};
