import React from 'react';
import { Link } from 'react-router-dom';
import { getChampionshipDetailPath } from '../../navigation/competitionRoutes';
import {
  getChampionshipStatusLabel,
  getChampionshipTypeLabel,
  getCompetitionDateRangeLabel,
} from '../../pages/Competition/competitionPresentation';
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

  const detailPath = getChampionshipDetailPath(id);
  const datesLabel = getCompetitionDateRangeLabel(start_date, end_date);

  return (
    <article className={styles.card}>
      <div className={styles.cardHeader}>
        <span className={styles.badge}>{getChampionshipTypeLabel(type)}</span>
        {registration_is_open && (
          <span className={styles.registrationBadge}>Inscripción abierta</span>
        )}
      </div>
      
      <div className={styles.cardContent}>
        <h2 className={styles.title}>{name}</h2>
        <p className={styles.seasonName}>{season?.name || 'Temporada no disponible'}</p>
        
        <dl className={styles.details}>
          <div className={styles.detailItem}>
            <dt className={styles.label}>Estado</dt>
            <dd className={styles.value}>{getChampionshipStatusLabel(status)}</dd>
          </div>
          {datesLabel ? (
            <div className={styles.detailItem}>
              <dt className={styles.label}>Fechas</dt>
              <dd className={styles.value}>{datesLabel}</dd>
            </div>
          ) : null}
        </dl>
      </div>

      <div className={styles.cardFooter}>
        {detailPath ? (
          <Link to={detailPath} className={styles.viewBtn}>Ver campeonato</Link>
        ) : null}
      </div>
    </article>
  );
};
