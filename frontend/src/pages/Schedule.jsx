import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../api/championships';
import { CategoryNavigation } from '../components/Competition/CategoryNavigation';
import MatchCard from '../components/MatchCard';
import { PageMetadata } from '../components/PublicLanding/PageMetadata';
import {
  getCategoryDetailPath,
  TOURNAMENTS_PATH,
} from '../navigation/competitionRoutes';
import { getMatchStatusLabel } from './Competition/competitionPresentation';
import styles from './Schedule.module.css';

export default function Schedule() {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [schedule, setSchedule] = useState([]);
  const [status, setStatus] = useState('loading');
  const [contextError, setContextError] = useState(false);

  const loadSchedule = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');
    setContextError(false);

    const [categoryResult, scheduleResult] = await Promise.allSettled([
      championshipsService.getCategory(categoryId),
      championshipsService.getCategorySchedule(categoryId),
    ]);

    if (request.current !== requestId) {
      return;
    }

    if (categoryResult.status === 'fulfilled' && categoryResult.value) {
      setCategory(categoryResult.value);
    } else {
      setCategory(null);
      setContextError(true);
    }

    if (scheduleResult.status === 'fulfilled' && Array.isArray(scheduleResult.value)) {
      setSchedule(scheduleResult.value);
      setStatus(scheduleResult.value.length > 0 ? 'content' : 'empty');
    } else {
      setSchedule([]);
      setStatus('error');
    }
  }, [categoryId]);

  useEffect(() => {
    void Promise.resolve().then(loadSchedule);

    return () => {
      request.current += 1;
    };
  }, [loadSchedule]);

  const categoryName = category?.name || 'Categoría no disponible';
  const championshipName = category?.championship?.name;
  const seasonName = category?.championship?.season?.name;
  const backPath = category ? getCategoryDetailPath(categoryId) : TOURNAMENTS_PATH;
  const backLabel = category ? 'Volver a la categoría' : 'Volver a Torneos';

  return (
    <div className="page-container">
      <PageMetadata
        title={`Calendario de ${categoryName} | Galotxas`}
        description={`Consulta el calendario y los resultados públicos de ${categoryName}.`}
      />
      <Link to={backPath} className={styles.backLink}>← {backLabel}</Link>
      <header className={styles.header}>
        <p className={styles.context}>
          {[seasonName, championshipName].filter(Boolean).join(' · ') || 'Contexto deportivo no disponible'}
        </p>
        <h1 className={styles.title}>Calendario y resultados de {categoryName}</h1>
      </header>

      <CategoryNavigation categoryId={categoryId} currentView="schedule" />

      {status === 'loading' ? (
        <p className={styles.stateMessage} role="status">Cargando calendario…</p>
      ) : null}
      {contextError && status !== 'loading' ? (
        <p className={styles.contextWarning} role="status">
          El calendario está disponible, pero no se ha podido cargar el contexto de la categoría.
        </p>
      ) : null}
      {status === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>No se ha podido cargar el calendario.</p>
          <button type="button" className={styles.retryButton} onClick={loadSchedule}>
            Reintentar
          </button>
        </div>
      ) : null}
      {status === 'empty' ? (
        <p className={styles.emptySchedule}>Todavía no hay jornadas configuradas para esta categoría.</p>
      ) : null}
      {status === 'content' ? schedule.map((round, roundIndex) => (
        <section key={round.id || `${categoryId}-${roundIndex}`} className={styles.roundSection}>
          <h2 className={styles.roundTitle}>{round.name || `Jornada ${roundIndex + 1}`}</h2>
          {Array.isArray(round.matches) && round.matches.length > 0 ? (
            <div className={styles.matchesGrid}>
              {round.matches.map((match, matchIndex) => (
                <MatchCard
                  key={match.id || `${round.id || roundIndex}-${matchIndex}`}
                  match={match}
                  translateStatus={getMatchStatusLabel}
                  officialScoresOnly
                  showDetailLabel
                  showVenue
                />
              ))}
            </div>
          ) : (
            <p className={styles.emptyMessage}>No hay partidos programados en esta jornada.</p>
          )}
        </section>
      )) : null}
    </div>
  );
}
