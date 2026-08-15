import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { AllTimeRanking } from '../../components/Rankings/AllTimeRanking';
import { CategoryRanking } from '../../components/Rankings/CategoryRanking';
import { ChampionshipRanking } from '../../components/Rankings/ChampionshipRanking';
import { SeasonRanking } from '../../components/Rankings/SeasonRanking';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { COMPETITION_PATH } from '../../navigation/competitionRoutes';
import styles from './Rankings.module.css';

const rankingTabs = Object.freeze([
  { id: 'all-time', label: 'Histórico' },
  { id: 'season', label: 'Temporada' },
  { id: 'championship', label: 'Campeonato' },
  { id: 'category', label: 'Categoría' },
]);

export const Rankings = () => {
  const request = useRef(0);
  const [seasons, setSeasons] = useState([]);
  const [selectedSeasonId, setSelectedSeasonId] = useState('');
  const [activeTab, setActiveTab] = useState('all-time');
  const [seasonStatus, setSeasonStatus] = useState('loading');

  const loadSeasons = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setSeasonStatus('loading');

    try {
      const data = await championshipsService.getSeasons();

      if (request.current === requestId) {
        const rows = Array.isArray(data) ? data : [];
        setSeasons(rows);
        setSelectedSeasonId((currentId) => (
          rows.some((season) => String(season.id) === String(currentId))
            ? currentId
            : rows[0]?.id || ''
        ));
        setSeasonStatus(rows.length > 0 ? 'content' : 'empty');
      }
    } catch {
      if (request.current === requestId) {
        setSeasons([]);
        setSelectedSeasonId('');
        setSeasonStatus('error');
      }
    }
  }, []);

  useEffect(() => {
    void Promise.resolve().then(loadSeasons);

    return () => {
      request.current += 1;
    };
  }, [loadSeasons]);

  return (
    <div className={styles.container}>
      <PageMetadata
        title="Rankings"
        description="Consulta los rankings públicos de Galotxas por histórico, temporada, campeonato y categoría."
      />
      <Link to={COMPETITION_PATH} className={styles.backLink}>← Volver a Competición</Link>
      <header className={styles.header}>
        <h1 className={styles.title}>Rankings de Galotxas</h1>
        <p className={styles.subtitle}>
          Consulta el rendimiento por histórico, temporada, campeonato o categoría.
        </p>
      </header>

      <div className={styles.tabs} role="tablist" aria-label="Tipos de ranking">
        {rankingTabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            id={`${tab.id}-ranking-tab`}
            role="tab"
            aria-selected={activeTab === tab.id}
            aria-controls={`${tab.id}-ranking-panel`}
            className={`${styles.tabBtn} ${activeTab === tab.id ? styles.activeTab : ''}`}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className={styles.content}>
        {activeTab === 'all-time' ? (
          <div
            id="all-time-ranking-panel"
            role="tabpanel"
            aria-labelledby="all-time-ranking-tab"
          >
            <AllTimeRanking />
          </div>
        ) : null}
        {activeTab === 'season' ? (
          <div
            id="season-ranking-panel"
            role="tabpanel"
            aria-labelledby="season-ranking-tab"
          >
            <SeasonRanking
              seasons={seasons}
              selectedSeasonId={selectedSeasonId}
              onSeasonChange={setSelectedSeasonId}
              seasonStatus={seasonStatus}
              onRetrySeasons={loadSeasons}
            />
          </div>
        ) : null}
        {activeTab === 'championship' ? (
          <div
            id="championship-ranking-panel"
            role="tabpanel"
            aria-labelledby="championship-ranking-tab"
          >
            <ChampionshipRanking
              seasons={seasons}
              seasonStatus={seasonStatus}
              onRetrySeasons={loadSeasons}
            />
          </div>
        ) : null}
        {activeTab === 'category' ? (
          <div
            id="category-ranking-panel"
            role="tabpanel"
            aria-labelledby="category-ranking-tab"
          >
            <CategoryRanking
              seasons={seasons}
              seasonStatus={seasonStatus}
              onRetrySeasons={loadSeasons}
            />
          </div>
        ) : null}
      </div>
    </div>
  );
};
