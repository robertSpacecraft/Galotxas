import React from 'react';
import { useSchedule } from '../../hooks/useSchedule';
import styles from './Schedule.module.css';

export const Schedule = ({ categoryId }) => {
  const { schedule, loading, error } = useSchedule(categoryId);

  if (!categoryId) return <div className={styles.loader}>Selecciona una categoría para ver el calendario.</div>;
  if (loading) return <div className={styles.loader}>Cargando calendario...</div>;
  if (error) return <div className={styles.error}>{error}</div>;

  const getStatusBadge = (status) => {
    switch(status) {
      case 'validated': return <span className={`${styles.badge} ${styles.bgPlayed}`}>Finalizado</span>;
      case 'under_review': return <span className={`${styles.badge} ${styles.bgConflict}`}>En Revisión</span>;
      case 'scheduled': 
      default:
        return <span className={`${styles.badge} ${styles.bgScheduled}`}>Programado</span>;
    }
  };

  return (
    <div className={styles.container}>
      <h2 className={styles.title}>Calendario de Partidos</h2>
      
      <div className={styles.matchList}>
        {schedule.length === 0 ? (
          <div style={{textAlign: 'center', padding: '2rem', color: '#6b7280'}}>
            No hay partidos programados.
          </div>
        ) : (
          schedule.map(match => (
            <div key={match.id} className={styles.matchCard}>
              <div className={styles.dateSection}>
                {match.scheduled_at 
                  ? new Date(match.scheduled_at).toLocaleDateString() 
                  : 'Por definir'}
              </div>
              
              <div className={styles.teamsSection}>
                <div className={`${styles.team} ${styles.teamHome}`}>
                  {match.home_team?.name || 'Local'}
                </div>
                
                <div className={styles.score}>
                  {match.status === 'validated' || (match.home_score !== null && match.away_score !== null)
                    ? `${match.home_score} - ${match.away_score}`
                    : 'vs'}
                </div>
                
                <div className={`${styles.team} ${styles.teamAway}`}>
                  {match.away_team?.name || 'Visitante'}
                </div>
              </div>
              
              <div className={styles.statusSection}>
                {getStatusBadge(match.status)}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};
