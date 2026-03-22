import React from 'react';
import { useMyMatches } from '../../hooks/useMyMatches';
import styles from './MyMatches.module.css';

// Using a basic router navigation approach. In a real app we'd use useNavigate from react-router-dom
export const MyMatches = ({ onManageMatch }) => {
  const { matches, loading, error } = useMyMatches();

  if (loading) return <div className={styles.loading}>Cargando partidos...</div>;
  if (error) return <div className={styles.error}>{error}</div>;

  if (!matches || matches.length === 0) {
    return (
      <div className={styles.container}>
        <h2 className={styles.title}>Mis Partidos</h2>
        <div className={styles.loading}>No tienes partidos asignados.</div>
      </div>
    );
  }

  const getStatusClass = (status) => {
    switch (status) {
      case 'scheduled': return styles.status_scheduled;
      case 'submitted': return styles.status_submitted;
      case 'validated': return styles.status_validated;
      case 'under_review': return styles.status_under_review;
      default: return styles.status_scheduled;
    }
  };

  const getStatusText = (status) => {
    switch (status) {
      case 'scheduled': return 'Programado';
      case 'submitted': return 'Resultado Enviado';
      case 'validated': return 'Validado';
      case 'under_review': return 'En Revisión';
      default: return 'Desconocido';
    }
  };

  return (
    <div className={styles.container}>
      <h2 className={styles.title}>Mis Partidos</h2>
      
      <div className={styles.grid}>
        {matches.map(match => (
          <div key={match.id} className={styles.card}>
            <div className={styles.matchHeader}>
              <span className={styles.matchCategory}>{match.category?.name || 'Categoría Única'}</span>
              <span className={`${styles.statusBadge} ${getStatusClass(match.status)}`}>
                {getStatusText(match.status)}
              </span>
            </div>
            
            <div className={styles.teams}>
              <div className={styles.teamRow}>
                <span className={styles.teamName}>{match.home_team?.name || 'Local'}</span>
                <span className={styles.teamScore}>
                  {match.home_score !== null ? match.home_score : '-'}
                </span>
              </div>
              <div className={styles.teamRow}>
                <span className={styles.teamName}>{match.away_team?.name || 'Visitante'}</span>
                <span className={styles.teamScore}>
                  {match.away_score !== null ? match.away_score : '-'}
                </span>
              </div>
            </div>

            <div className={styles.date}>
              {match.scheduled_at 
                ? new Date(match.scheduled_at).toLocaleDateString() 
                : 'Fecha por definir'}
            </div>

            <button 
              className={styles.actionButton}
              onClick={() => onManageMatch && onManageMatch(match.id)}
            >
              Gestionar Resultado
            </button>
          </div>
        ))}
      </div>
    </div>
  );
};
