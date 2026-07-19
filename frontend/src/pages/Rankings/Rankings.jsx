import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { AllTimeRanking } from '../../components/Rankings/AllTimeRanking';
import { SeasonRanking } from '../../components/Rankings/SeasonRanking';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { COMPETITION_PATH } from '../../navigation/competitionRoutes';
import styles from './Rankings.module.css';

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
        title="Rankings | Competición | Galotxas"
        description="Consulta el ranking histórico de Galotxas y los rankings por temporada."
      />
      <Link to={COMPETITION_PATH} className={styles.backLink}>← Volver a Competición</Link>
      <header className={styles.header}>
        <h1 className={styles.title}>Rankings de Galotxas</h1>
        <p className={styles.subtitle}>
          Consulta el rendimiento histórico y el ranking de cada temporada.
        </p>
      </header>

      <div className={styles.tabs} role="tablist" aria-label="Tipos de ranking">
        <button
          type="button"
          id="all-time-ranking-tab"
          role="tab"
          aria-selected={activeTab === 'all-time'}
          aria-controls="all-time-ranking-panel"
          className={`${styles.tabBtn} ${activeTab === 'all-time' ? styles.activeTab : ''}`}
          onClick={() => setActiveTab('all-time')}
        >
          Ranking histórico
        </button>
        <button
          type="button"
          id="season-ranking-tab"
          role="tab"
          aria-selected={activeTab === 'season'}
          aria-controls="season-ranking-panel"
          className={`${styles.tabBtn} ${activeTab === 'season' ? styles.activeTab : ''}`}
          onClick={() => setActiveTab('season')}
        >
          Ranking de temporada
        </button>
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
      </div>
    </div>
  );
};
