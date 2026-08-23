import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { CategoryNavigation } from '../../components/Competition/CategoryNavigation';
import { CupBracket } from '../../components/Competition/CupBracket';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { selectCategoryCupRounds } from '../../features/competition/categoryScheduleContract';
import {
  getCategoryDetailPath,
  TOURNAMENTS_PATH,
} from '../../navigation/competitionRoutes';
import styles from '../Schedule.module.css';

export default function CupPage() {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [cupRounds, setCupRounds] = useState([]);
  const [status, setStatus] = useState('loading');
  const [contextError, setContextError] = useState(false);

  const loadCup = useCallback(async () => {
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
      const rounds = selectCategoryCupRounds(scheduleResult.value);
      setCupRounds(rounds);
      setStatus(rounds.length > 0 ? 'content' : 'empty');
    } else {
      setCupRounds([]);
      setStatus('error');
    }
  }, [categoryId]);

  useEffect(() => {
    void Promise.resolve().then(loadCup);

    return () => {
      request.current += 1;
    };
  }, [loadCup]);

  const categoryName = category?.name || 'Categoría no disponible';
  const championshipName = category?.championship?.name;
  const seasonName = category?.championship?.season?.name;
  const backPath = category ? getCategoryDetailPath(categoryId) : TOURNAMENTS_PATH;
  const backLabel = category ? 'Volver a la categoría' : 'Volver a Torneos';

  return (
    <div className="page-container">
      <PageMetadata
        title="Copa"
        description="Consulta el cuadro público de Copa de una categoría."
      />
      <Link to={backPath} className={styles.backLink}>← {backLabel}</Link>
      <header className={styles.header}>
        <p className={styles.context}>
          {[seasonName, championshipName].filter(Boolean).join(' · ') || 'Contexto deportivo no disponible'}
        </p>
        <h1 className={styles.title}>Copa de {categoryName}</h1>
      </header>

      <CategoryNavigation categoryId={categoryId} currentView="cup" />

      {status === 'loading' ? (
        <p className={styles.stateMessage} role="status">Cargando Copa…</p>
      ) : null}
      {contextError && status !== 'loading' ? (
        <p className={styles.contextWarning} role="status">
          La Copa está disponible, pero no se ha podido cargar el contexto de la categoría.
        </p>
      ) : null}
      {status === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>No se ha podido cargar la Copa.</p>
          <button type="button" className={styles.retryButton} onClick={loadCup}>
            Reintentar
          </button>
        </div>
      ) : null}
      {status === 'empty' ? (
        <p className={styles.emptySchedule}>
          Todavía no hay una Copa generada para esta categoría.
        </p>
      ) : null}
      {status === 'content' ? <CupBracket rounds={cupRounds} /> : null}
    </div>
  );
}
