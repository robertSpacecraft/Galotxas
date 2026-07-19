import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../api/championships';
import { CategoryNavigation } from '../components/Competition/CategoryNavigation';
import { PageMetadata } from '../components/PublicLanding/PageMetadata';
import {
  getCategoryDetailPath,
  TOURNAMENTS_PATH,
} from '../navigation/competitionRoutes';
import styles from './Standings.module.css';

const displayValue = (value) => (
  value === null || value === undefined || value === '' ? '—' : value
);

const displayDifference = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return Number(value) > 0 ? `+${value}` : value;
};

export default function Standings() {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [standings, setStandings] = useState([]);
  const [status, setStatus] = useState('loading');
  const [contextError, setContextError] = useState(false);

  const loadStandings = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');
    setContextError(false);

    const [categoryResult, standingsResult] = await Promise.allSettled([
      championshipsService.getCategory(categoryId),
      championshipsService.getCategoryStandings(categoryId),
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

    if (standingsResult.status === 'fulfilled' && Array.isArray(standingsResult.value)) {
      setStandings(standingsResult.value);
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
  const backLabel = category ? 'Volver a la categoría' : 'Volver a Torneos';

  return (
    <div className="page-container">
      <PageMetadata
        title={`Clasificación de ${categoryName} | Galotxas`}
        description={`Consulta la clasificación pública de ${categoryName}.`}
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
      {contextError && status !== 'loading' ? (
        <p className={styles.contextWarning} role="status">
          La clasificación está disponible, pero no se ha podido cargar el contexto de la categoría.
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
        <div
          className={styles.tableWrapper}
          role="region"
          aria-label={`Tabla de clasificación de ${categoryName}`}
          tabIndex="0"
        >
          <table className={styles.table}>
            <caption className={styles.visuallyHidden}>Clasificación de {categoryName}</caption>
            <thead>
              <tr className={styles.headerRow}>
                <th scope="col" className={styles.pos}>Pos.</th>
                <th scope="col">Participante</th>
                <th scope="col" className={styles.center}><abbr title="Partidos jugados">PJ</abbr></th>
                <th scope="col" className={styles.center}><abbr title="Victorias">V</abbr></th>
                <th scope="col" className={styles.center}><abbr title="Derrotas">D</abbr></th>
                <th scope="col" className={styles.center}><abbr title="Juegos a favor">JF</abbr></th>
                <th scope="col" className={styles.center}><abbr title="Juegos en contra">JC</abbr></th>
                <th scope="col" className={styles.center}><abbr title="Diferencia de juegos">Dif.</abbr></th>
                <th scope="col" className={styles.center}>Puntos</th>
              </tr>
            </thead>
            <tbody>
              {standings.map((row) => (
                <tr key={row.entry_id} className={styles.row}>
                  <td className={styles.pos}>{displayValue(row.position)}</td>
                  <th scope="row" className={styles.name}>{row.name || 'Participante no disponible'}</th>
                  <td className={styles.center}>{displayValue(row.played)}</td>
                  <td className={styles.center}>{displayValue(row.wins)}</td>
                  <td className={styles.center}>{displayValue(row.losses)}</td>
                  <td className={styles.center}>{displayValue(row.games_for)}</td>
                  <td className={styles.center}>{displayValue(row.games_against)}</td>
                  <td className={styles.center}>{displayDifference(row.games_diff)}</td>
                  <td className={styles.points}>{displayValue(row.points)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
