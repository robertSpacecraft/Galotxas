import { Link } from 'react-router-dom';
import styles from './PendingMatchActions.module.css';

const actionLabels = {
  submit_result: 'Enviar resultado',
  confirm_result: 'Confirmar resultado',
  under_review: 'Resultado en revisión',
};

const actionDescriptions = {
  submit_result: 'El partido todavía no tiene ningún reporte.',
  confirm_result: 'El lado rival ha enviado un resultado pendiente de tu revisión.',
  under_review: 'Existe una discrepancia pendiente de resolución administrativa.',
};

const getEntryName = (entry) => {
  if (!entry) {
    return 'Por determinar';
  }

  if (entry.team) {
    return entry.team.name || 'Equipo';
  }

  if (entry.player) {
    return entry.player.nickname
      || `${entry.player.name || ''} ${entry.player.lastname || ''}`.trim()
      || 'Jugador';
  }

  return 'Participante';
};

const formatDateTime = (value) => {
  if (!value) {
    return 'Fecha por determinar';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
};

export function PendingMatchActions({ actions, loading, error }) {
  const safeActions = Array.isArray(actions) ? actions : [];

  return (
    <section className={styles.section} aria-labelledby="pending-match-actions-title">
      <div className={styles.header}>
        <div>
          <h2 id="pending-match-actions-title" className={styles.title}>Acciones pendientes</h2>
          <p className={styles.subtitle}>Partidos que necesitan tu atención.</p>
        </div>
        {!loading && !error ? (
          <span className={styles.count} aria-label={`${safeActions.length} acciones pendientes`}>
            {safeActions.length}
          </span>
        ) : null}
      </div>

      {loading ? <p className={styles.state} role="status">Cargando acciones pendientes...</p> : null}

      {!loading && error ? <p className={styles.error} role="alert">{error}</p> : null}

      {!loading && !error && safeActions.length === 0 ? (
        <p className={styles.empty}>No tienes acciones pendientes.</p>
      ) : null}

      {!loading && !error && safeActions.length > 0 ? (
        <div className={styles.list}>
          {safeActions.map(({ type, match }) => {
            const category = match?.round?.category;
            const championship = category?.championship;

            return (
              <article key={`${type}-${match.id}`} className={styles.card}>
                <div className={styles.cardContent}>
                  <span className={`${styles.actionType} ${type === 'under_review' ? styles.reviewType : ''}`}>
                    {actionLabels[type] || type}
                  </span>
                  <h3 className={styles.participants}>
                    {getEntryName(match.home_entry)} <span>vs</span> {getEntryName(match.away_entry)}
                  </h3>
                  <p className={styles.description}>{actionDescriptions[type]}</p>
                  <p className={styles.meta}>
                    {[championship?.name, category?.name].filter(Boolean).join(' · ') || 'Competición'}
                  </p>
                  <p className={styles.meta}>{formatDateTime(match.scheduled_date)}</p>
                </div>
                <Link className={styles.actionLink} to={`/matches/${match.id}`}>
                  {type === 'under_review' ? 'Ver revisión' : actionLabels[type]}
                </Link>
              </article>
            );
          })}
        </div>
      ) : null}
    </section>
  );
}
