import React from 'react';
import { useStandings } from '../../hooks/useStandings';
import styles from './Standings.module.css';

export const Standings = ({ categoryId }) => {
  const { standings, loading, error } = useStandings(categoryId);

  if (!categoryId) return <div className={styles.loader}>Selecciona una categoría para ver la clasificación.</div>;
  if (loading) return <div className={styles.loader}>Cargando clasificación...</div>;
  if (error) return <div className={styles.error}>{error}</div>;

  return (
    <div className={styles.container}>
      <h2 className={styles.title}>Clasificación</h2>
      
      <div className={styles.tableWrapper}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th className={styles.th}>Pos</th>
              <th className={styles.th}>Equipo</th>
              <th className={styles.th}>PJ</th>
              <th className={styles.th}>G</th>
              <th className={styles.th}>E</th>
              <th className={styles.th}>P</th>
              <th className={styles.th}>PTS</th>
            </tr>
          </thead>
          <tbody>
            {standings.map((entry, index) => (
              <tr key={index} className={styles.tr}>
                <td className={styles.td}>
                  <span className={styles.position}>{index + 1}</span>
                </td>
                <td className={styles.td}>
                  <div className={styles.teamName}>
                    {entry.team?.name || entry.name || 'Equipo'}
                  </div>
                </td>
                <td className={styles.td}>{entry.matches_played || 0}</td>
                <td className={styles.td}>{entry.won || 0}</td>
                <td className={styles.td}>{entry.drawn || 0}</td>
                <td className={styles.td}>{entry.lost || 0}</td>
                <td className={`${styles.td} ${styles.pts}`}>{entry.points || 0}</td>
              </tr>
            ))}
            {standings.length === 0 && (
              <tr>
                <td colSpan="7" className={styles.td} style={{textAlign: 'center', padding: '2rem'}}>
                  No hay datos en la clasificación.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
};
