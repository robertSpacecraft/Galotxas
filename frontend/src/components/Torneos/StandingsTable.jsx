import React from 'react';
import styles from './StandingsTable.module.css';

export const StandingsTable = ({ standings }) => {
  if (!standings || standings.length === 0) {
    return <p className={styles.noData}>No hay participantes o resultados todavía.</p>;
  }

  return (
    <div className={styles.tableWrapper}>
      <table className={styles.table}>
        <thead>
          <tr>
            <th className={styles.center}>#</th>
            <th>Participante</th>
            <th className={styles.center}>PJ</th>
            <th className={styles.center}>V</th>
            <th className={styles.center}>D</th>
            <th className={styles.center}>JF</th>
            <th className={styles.center}>JC</th>
            <th className={styles.center}>Dif</th>
            <th className={`${styles.center} ${styles.highlight}`}>PTS</th>
          </tr>
        </thead>
        <tbody>
          {standings.map((row, index) => (
            <tr key={row.entry_id || index}>
              <td className={`${styles.center} ${styles.pos} ${index < 4 ? styles.leader : ''}`}>
                {index + 1}
              </td>
              <td className={styles.name}>{row.name}</td>
              <td className={styles.center}>{row.played}</td>
              <td className={`${styles.center} ${styles.win}`}>{row.wins}</td>
              <td className={`${styles.center} ${styles.loss}`}>{row.losses}</td>
              <td className={styles.center}>{row.games_for}</td>
              <td className={styles.center}>{row.games_against}</td>
              <td className={`${styles.center} ${row.games_diff > 0 ? styles.win : (row.games_diff < 0 ? styles.loss : '')}`}>
                {row.games_diff > 0 ? `+${row.games_diff}` : row.games_diff}
              </td>
              <td className={`${styles.center} ${styles.points}`}>{row.points}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
