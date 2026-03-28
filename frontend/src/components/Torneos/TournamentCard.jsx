import React from 'react';
import { Link } from 'react-router-dom';
import styles from './TournamentCard.module.css';

export const TournamentCard = ({ tournament }) => {
  const {
    id,
    name,
    type,
    status,
    start_date,
    end_date,
    registration_is_open,
    season
  } = tournament;

  const getStatusLabel = (status) => {
    const statuses = {
      draft: 'Borrador',
      published: 'Próximamente',
      ongoing: 'En curso',
      active: 'En curso',
      finished: 'Finalizado'
    };
    return statuses[status] || status;
  };

  return (
    <div className={styles.card}>
      <div className={styles.cardHeader}>
        <span className={`${styles.badge} ${styles[type]}`}>{type}</span>
        {registration_is_open && (
          <span className={styles.registrationBadge}>Inscripción Abierta</span>
        )}
      </div>
      
      <div className={styles.cardContent}>
        <h3 className={styles.title}>{name}</h3>
        <p className={styles.seasonName}>{season?.name}</p>
        
        <div className={styles.details}>
          <div className={styles.detailItem}>
            <span className={styles.label}>Estado:</span>
            <span className={styles.value}>{getStatusLabel(status)}</span>
          </div>
          <div className={styles.detailItem}>
            <span className={styles.label}>Fechas:</span>
            <span className={styles.value}>
              {new Date(start_date).toLocaleDateString()} - {new Date(end_date).toLocaleDateString()}
            </span>
          </div>
        </div>
      </div>

      <div className={styles.cardFooter}>
        <Link to={`/torneos/${id}`} className={styles.viewBtn}>Ver Torneo</Link>
        {registration_is_open && (
           <Link to={`/torneos/${id}`} className={styles.registerBtn}>Inscribirme</Link>
        )}
      </div>
    </div>
  );
};
