import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../api/championships';
import { CategoryNavigation } from '../components/Competition/CategoryNavigation';
import { PageMetadata } from '../components/PublicLanding/PageMetadata';
import { CategoryRankingTable } from '../components/Rankings/CategoryRankingTable';
import {
  getCategoryDetailPath,
  TOURNAMENTS_PATH,
} from '../navigation/competitionRoutes';
import styles from './Standings.module.css';

export default function Standings() {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [standings, setStandings] = useState([]);
  const [status, setStatus] = useState('loading');
  const [contextError, setContextError] = useState(false);
  const [classificationKind, setClassificationKind] = useState('current');
  const [officialResultsError, setOfficialResultsError] = useState(false);

  const loadStandings = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');
    setContextError(false);
    setClassificationKind('current');
    setOfficialResultsError(false);

    const [categoryResult, standingsResult, officialResultsResult] = await Promise.allSettled([
      championshipsService.getCategory(categoryId),
      championshipsService.getCategoryStandings(categoryId),
      championshipsService.getCategoryOfficialResults(categoryId),
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

    const officialRanking = officialResultsResult.status === 'fulfilled'
      && Array.isArray(officialResultsResult.value?.league?.ranking)
      ? officialResultsResult.value.league.ranking
      : null;

    setOfficialResultsError(officialResultsResult.status === 'rejected');

    if (officialRanking) {
      setStandings(officialRanking);
      setClassificationKind('official');
      setStatus(officialRanking.length > 0 ? 'content' : 'empty');
    } else if (standingsResult.status === 'fulfilled' && Array.isArray(standingsResult.value)) {
      setStandings(standingsResult.value);
      setClassificationKind('current');
      setStatus(standingsResult.value.length > 0 ? 'content' : 'empty');
    } else {
      setStandings([]);
      setStatus('error');
    }
  }, [categoryId]);

  useEffect(() => {
    void Promise.resolve().then(loadStandings);

    return () => {
      request.current += 1;
    };
  }, [loadStandings]);

  const categoryName = category?.name || 'Categoría no disponible';
  const championshipName = category?.championship?.name;
  const seasonName = category?.championship?.season?.name;
  const backPath = category ? getCategoryDetailPath(categoryId) : TOURNAMENTS_PATH;
  const backLabel = category ? 'Volver a la categoría' : 'Volver a Campeonatos';
  const classificationTitle = classificationKind === 'official'
    ? 'Clasificación oficial'
    : 'Clasificación actual';

  return (
    <div className="page-container">
      <PageMetadata
        title="Clasificación"
        description="Consulta una clasificación pública de Galotxas con identidad minimizada."
      />
      <Link to={backPath} className={styles.backLink}>← {backLabel}</Link>
      <header className={styles.header}>
        <div>
          <p className={styles.context}>
            {[seasonName, championshipName].filter(Boolean).join(' · ') || 'Contexto deportivo no disponible'}
          </p>
          <h1 className={styles.title}>Clasificación de {categoryName}</h1>
        </div>
      </header>

      <CategoryNavigation categoryId={categoryId} currentView="standings" />

      {status === 'loading' ? (
        <p className={styles.stateMessage} role="status">Cargando clasificación…</p>
      ) : null}
      {status !== 'loading' && status !== 'error' ? (
        <h2 className={styles.sectionTitle}>{classificationTitle}</h2>
      ) : null}
      {contextError && status !== 'loading' ? (
        <p className={styles.contextWarning} role="status">
          La clasificación está disponible, pero no se ha podido cargar el contexto de la categoría.
        </p>
      ) : null}
      {officialResultsError && (status === 'content' || status === 'empty') ? (
        <p className={styles.contextWarning} role="status">
          No se ha podido comprobar el resultado oficial. Se muestra la clasificación actual.
        </p>
      ) : null}
      {status === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>No se ha podido cargar la clasificación.</p>
          <button type="button" className={styles.retryButton} onClick={loadStandings}>
            Reintentar
          </button>
        </div>
      ) : null}
      {status === 'empty' ? (
        <p className={styles.emptyMessage}>Todavía no hay participantes o resultados en esta clasificación.</p>
      ) : null}
      {status === 'content' ? (
        <CategoryRankingTable
          ranking={standings}
          categoryName={categoryName}
          tableStyles={styles}
        />
      ) : null}
    </div>
  );
}
