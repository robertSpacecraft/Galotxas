import { useEffect, useRef, useState } from 'react';
import { championshipsService } from '../../api/championships';
import { TournamentRanking } from '../Torneos/TournamentRanking';
import styles from './RankingTables.module.css';
import { findById, firstId, keepValidId } from './rankingHierarchy';

export const ChampionshipRanking = ({ seasons, seasonStatus, onRetrySeasons }) => {
  const request = useRef(0);
  const [requestedSeasonId, setRequestedSeasonId] = useState('');
  const [requestedChampionshipId, setRequestedChampionshipId] = useState('');
  const [ranking, setRanking] = useState([]);
  const [status, setStatus] = useState('idle');
  const [retryVersion, setRetryVersion] = useState(0);

  const selectedSeasonId = keepValidId(seasons, requestedSeasonId);
  const selectedSeason = findById(seasons, selectedSeasonId);
  const championships = Array.isArray(selectedSeason?.championships)
    ? selectedSeason.championships
    : [];
  const selectedChampionshipId = keepValidId(championships, requestedChampionshipId);

  useEffect(() => {
    const loadRanking = async () => {
      if (!selectedChampionshipId) {
        setRanking([]);
        setStatus('idle');
        return;
      }

      const requestId = request.current + 1;
      request.current = requestId;
      setStatus('loading');

      try {
        const data = await championshipsService.getChampionshipRanking(selectedChampionshipId);

        if (request.current === requestId) {
          const rows = Array.isArray(data) ? data : [];
          setRanking(rows);
          setStatus(rows.length > 0 ? 'content' : 'empty');
        }
      } catch {
        if (request.current === requestId) {
          setRanking([]);
          setStatus('error');
        }
      }
    };

    void Promise.resolve().then(loadRanking);

    return () => {
      request.current += 1;
    };
  }, [retryVersion, selectedChampionshipId]);

  const handleSeasonChange = (event) => {
    const seasonId = event.target.value;
    const nextChampionships = findById(seasons, seasonId)?.championships ?? [];

    request.current += 1;
    setRequestedSeasonId(seasonId);
    setRequestedChampionshipId(firstId(nextChampionships));
    setRanking([]);
    setStatus(nextChampionships.length > 0 ? 'loading' : 'idle');
  };

  const handleChampionshipChange = (event) => {
    request.current += 1;
    setRequestedChampionshipId(event.target.value);
    setRanking([]);
    setStatus('loading');
  };

  if (seasonStatus === 'loading') {
    return <p className={styles.loading} role="status">Cargando temporadas…</p>;
  }

  if (seasonStatus === 'error') {
    return (
      <div className={styles.error} role="alert">
        <p>No se han podido cargar las temporadas.</p>
        <button type="button" className={styles.retryButton} onClick={onRetrySeasons}>
          Reintentar temporadas
        </button>
      </div>
    );
  }

  if (seasonStatus === 'empty') {
    return <p className={styles.noData}>No hay temporadas disponibles para consultar campeonatos.</p>;
  }

  return (
    <section className={styles.rankingBox} aria-labelledby="championship-ranking-title">
      <h2 id="championship-ranking-title" className={styles.sectionTitle}>
        Ranking de campeonato
      </h2>
      <div className={styles.filterBar}>
        <div className={styles.filterField}>
          <label htmlFor="championship-ranking-season">Temporada del campeonato</label>
          <select
            id="championship-ranking-season"
            value={selectedSeasonId}
            onChange={handleSeasonChange}
            className={styles.select}
          >
            {seasons.map((season) => (
              <option key={season.id} value={season.id}>{season.name}</option>
            ))}
          </select>
        </div>
        <div className={styles.filterField}>
          <label htmlFor="championship-ranking-championship">Campeonato</label>
          <select
            id="championship-ranking-championship"
            value={selectedChampionshipId}
            onChange={handleChampionshipChange}
            disabled={championships.length === 0}
            className={styles.select}
          >
            {championships.map((championship) => (
              <option key={championship.id} value={championship.id}>{championship.name}</option>
            ))}
          </select>
        </div>
        {status === 'loading' ? (
          <span className={styles.inlineLoading} role="status">Actualizando…</span>
        ) : null}
      </div>

      {championships.length === 0 ? (
        <p className={styles.noData}>Esta temporada no tiene campeonatos públicos disponibles.</p>
      ) : null}
      {championships.length > 0 && status === 'error' ? (
        <div className={styles.error} role="alert">
          <p>No se ha podido cargar el ranking del campeonato.</p>
          <button
            type="button"
            className={styles.retryButton}
            onClick={() => setRetryVersion((version) => version + 1)}
          >
            Reintentar ranking
          </button>
        </div>
      ) : null}
      {championships.length > 0 && status === 'empty' ? (
        <p className={styles.noData}>Todavía no hay datos de ranking para este campeonato.</p>
      ) : null}
      {championships.length > 0 && status === 'content' ? (
        <TournamentRanking ranking={ranking} showTitle={false} />
      ) : null}
    </section>
  );
};
