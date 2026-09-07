import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { CategoryNavigation } from '../../components/Competition/CategoryNavigation';
import { CompetitionCoverImage } from '../../components/Competition/CompetitionCoverImage';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import {
  getCategoryCupPath,
  getCategoryStandingsPath,
  getChampionshipDetailPath,
  TOURNAMENTS_PATH,
} from '../../navigation/competitionRoutes';
import {
  getCategoryGenderLabel,
  getCategoryLevelLabel,
  getCategoryStatusLabel,
  getChampionshipTypeLabel,
} from '../Competition/competitionPresentation';
import styles from './Torneos.module.css';

const PODIUM_BADGE_CLASS = {
  1: styles.officialPodiumBadgeGold,
  2: styles.officialPodiumBadgeSilver,
  3: styles.officialPodiumBadgeBronze,
};

export const CategoryDetail = () => {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [status, setStatus] = useState('loading');
  const [officialResults, setOfficialResults] = useState(null);
  const [officialResultsStatus, setOfficialResultsStatus] = useState('loading');

  const loadCategory = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');
    setOfficialResults(null);
    setOfficialResultsStatus('loading');

    const categoryTask = championshipsService.getCategory(categoryId)
      .then((data) => {
        if (request.current !== requestId) return;
        setCategory(data || null);
        setStatus(data ? 'content' : 'error');
      })
      .catch(() => {
        if (request.current !== requestId) return;
        setCategory(null);
        setStatus('error');
      });
    const officialResultsTask = championshipsService.getCategoryOfficialResults(categoryId)
      .then((data) => {
        if (request.current !== requestId) return;
        setOfficialResults(data || { league: null, cup: null });
        setOfficialResultsStatus('content');
      })
      .catch(() => {
        if (request.current !== requestId) return;
        setOfficialResults(null);
        setOfficialResultsStatus('error');
      });

    await Promise.all([categoryTask, officialResultsTask]);
  }, [categoryId]);

  useEffect(() => {
    void Promise.resolve().then(loadCategory);

    return () => {
      request.current += 1;
    };
  }, [loadCategory]);

  if (status !== 'content') {
    return (
      <div className={styles.container}>
        <PageMetadata
          title="Categoría"
          description="Consulta el resumen de una categoría pública de Galotxas."
        />
        <Link to={TOURNAMENTS_PATH} className={styles.backLink}>← Volver a Campeonatos</Link>
        <h1 className={styles.title}>Detalle de la categoría</h1>
        {status === 'loading' ? (
          <p className={styles.loading} role="status">Cargando categoría…</p>
        ) : (
          <div className={styles.errorState} role="alert">
            <p>No se ha podido cargar la categoría.</p>
            <button type="button" className={styles.retryButton} onClick={loadCategory}>
              Reintentar
            </button>
          </div>
        )}
      </div>
    );
  }

  const championship = category.championship;
  const championshipPath = getChampionshipDetailPath(
    championship?.id || category.championship_id,
  );
  const seasonName = championship?.season?.name;
  const leagueRanking = officialResults?.league?.ranking;
  const leaguePodium = Array.isArray(leagueRanking)
    ? leagueRanking.slice(0, 3).filter((entry) => (
      Number.isFinite(entry?.position)
      && typeof entry.public_display_name === 'string'
      && entry.public_display_name.trim() !== ''
    ))
    : [];
  const cupChampion = officialResults?.cup?.champion?.public_display_name || null;
  const hasOfficialResult = Boolean(leaguePodium.length || cupChampion);
  const standingsPath = getCategoryStandingsPath(categoryId);
  const cupPath = getCategoryCupPath(categoryId);

  return (
    <div className={styles.container}>
      <PageMetadata
        title="Categoría"
        description="Consulta el resumen, la clasificación y el calendario públicos de la categoría."
      />
      {championshipPath ? (
        <Link to={championshipPath} className={styles.backLink}>
          ← Volver al campeonato
        </Link>
      ) : null}

      <header className={styles.detailHeader}>
        <CompetitionCoverImage
          image={category.image}
          className={styles.detailCover}
          priority
        />
        <div className={styles.headerInfo}>
          <p className={styles.contextPath}>
            {seasonName ? `${seasonName} · ` : ''}
            {championship?.name || 'Campeonato no disponible'}
          </p>
          <h1 className={styles.detailTitle}>{category.name}</h1>
        </div>
      </header>

      <CategoryNavigation categoryId={categoryId} currentView="detail" />

      <section className={styles.categorySummary} aria-labelledby="category-summary-title">
        <h2 id="category-summary-title" className={styles.subTitle}>Datos de la categoría</h2>
        <dl className={styles.summaryDetails}>
          <div>
            <dt>Estado</dt>
            <dd>{getCategoryStatusLabel(category.status)}</dd>
          </div>
          <div>
            <dt>Categoría</dt>
            <dd>{getCategoryGenderLabel(category.gender)}</dd>
          </div>
          <div>
            <dt>Nivel</dt>
            <dd>{getCategoryLevelLabel(category.level)}</dd>
          </div>
          <div>
            <dt>Modalidad del campeonato</dt>
            <dd>{getChampionshipTypeLabel(championship?.type)}</dd>
          </div>
        </dl>
      </section>

      <section className={styles.officialResults} aria-labelledby="official-results-title">
        <h2 id="official-results-title" className={styles.subTitle}>Resultados oficiales</h2>

        {officialResultsStatus === 'loading' ? (
          <p className={styles.officialResultsState} role="status">
            Cargando resultados oficiales…
          </p>
        ) : null}
        {officialResultsStatus === 'error' ? (
          <p className={styles.officialResultsWarning} role="status">
            No se ha podido comprobar el resultado oficial en este momento.
          </p>
        ) : null}
        {officialResultsStatus === 'content' && !hasOfficialResult ? (
          <p className={styles.officialResultsState}>
            Todavía no hay resultados oficiales vigentes para esta categoría.
          </p>
        ) : null}
        {officialResultsStatus === 'content' && hasOfficialResult ? (
          <div className={styles.officialResultsGrid}>
            <article className={styles.officialResultCard} aria-labelledby="official-league-title">
              <h3 id="official-league-title">Liga</h3>
              {leaguePodium.length > 0 ? (
                <>
                  <ol className={styles.officialPodium} aria-label="Podio oficial de Liga">
                    {leaguePodium.map((entry) => (
                      <li key={`${entry.position}-${entry.public_display_name}`}>
                        <span className={[
                          styles.officialPodiumBadge,
                          PODIUM_BADGE_CLASS[entry.position],
                        ].filter(Boolean).join(' ')}>
                          {entry.position}.º
                        </span>
                        <span className={styles.officialPodiumName}>
                          {entry.public_display_name}
                        </span>
                      </li>
                    ))}
                  </ol>
                  <Link className={styles.officialResultLink} to={standingsPath}>
                    Ver Clasificación
                  </Link>
                </>
              ) : (
                <p className={styles.officialResultsState}>Sin resultado oficial vigente</p>
              )}
            </article>
            <article className={styles.officialResultCard} aria-labelledby="official-cup-title">
              <h3 id="official-cup-title">Copa</h3>
              {cupChampion ? (
                <>
                  <p className={styles.officialResultLabel}>Ganador</p>
                  <p className={styles.officialChampion}>{cupChampion}</p>
                  <Link className={styles.officialResultLink} to={cupPath}>
                    Ver Copa
                  </Link>
                </>
              ) : (
                <p className={styles.officialResultsState}>Sin resultado oficial vigente</p>
              )}
            </article>
          </div>
        ) : null}
      </section>
    </div>
  );
};
