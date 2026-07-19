import { useCallback, useEffect, useRef, useState } from 'react';
import { championshipsService } from '../../api/championships';
import { formatCompetitionNumber } from '../../pages/Competition/competitionPresentation';
import styles from './RankingTables.module.css';

export const SeasonRanking = ({
  seasons,
  selectedSeasonId,
  onSeasonChange,
  seasonStatus,
  onRetrySeasons,
}) => {
  const request = useRef(0);
  const [ranking, setRanking] = useState([]);
  const [status, setStatus] = useState(selectedSeasonId ? 'loading' : 'idle');

  const loadRanking = useCallback(async () => {
    if (!selectedSeasonId) {
      setRanking([]);
      setStatus('idle');
      return;
    }

    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');

    try {
      const data = await championshipsService.getSeasonRanking(selectedSeasonId);

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
  }, [selectedSeasonId]);

  useEffect(() => {
    void Promise.resolve().then(loadRanking);

    return () => {
      request.current += 1;
    };
  }, [loadRanking]);

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
    return <p className={styles.noData}>No hay temporadas disponibles para consultar su ranking.</p>;
  }

  return (
    <section className={styles.rankingBox} aria-labelledby="season-ranking-title">
      <h2 id="season-ranking-title" className={styles.sectionTitle}>Ranking de temporada</h2>
      <div className={styles.filterBar}>
        <label htmlFor="season-select">Temporada</label>
        <select
          id="season-select"
          value={selectedSeasonId}
          onChange={(event) => onSeasonChange(event.target.value)}
          disabled={status === 'loading'}
          className={styles.select}
        >
          {seasons.map((season) => (
            <option key={season.id} value={season.id}>{season.name}</option>
          ))}
        </select>
        {status === 'loading' ? <span className={styles.inlineLoading} role="status">Actualizando…</span> : null}
      </div>

      {status === 'error' ? (
        <div className={styles.error} role="alert">
          <p>No se ha podido cargar el ranking de la temporada.</p>
          <button type="button" className={styles.retryButton} onClick={loadRanking}>
            Reintentar ranking
          </button>
        </div>
      ) : null}
      {status === 'empty' ? (
        <p className={styles.noData}>Todavía no hay datos de ranking para esta temporada.</p>
      ) : null}
      {status === 'content' ? (
        <div
          className={styles.tableWrapper}
          role="region"
          aria-label="Tabla del ranking de temporada"
          tabIndex="0"
        >
          <table className={styles.table}>
            <caption className={styles.visuallyHidden}>Ranking de la temporada seleccionada</caption>
            <thead>
              <tr>
                <th scope="col">Pos.</th>
                <th scope="col">Jugador</th>
                <th scope="col" className={styles.center}>PJ</th>
                <th scope="col" className={styles.center}>PG</th>
                <th scope="col" className={styles.center}>PP</th>
                <th scope="col" className={styles.center}>Puntos ponderados</th>
                <th scope="col" className={styles.center}>JF</th>
                <th scope="col" className={styles.center}>JC</th>
                <th scope="col" className={styles.center}>Dif.</th>
                <th scope="col" className={styles.center}>Categorías</th>
              </tr>
            </thead>
            <tbody>
              {ranking.map((row) => (
                <tr key={row.player_id || `${row.position}-${row.name}`}>
                  <td className={styles.positionCell}>
                    <span className={styles.posNum}>{row.position ?? '—'}</span>
                  </td>
                  <th scope="row" className={styles.playerName}>
                    {row.name || 'Jugador no disponible'}
                  </th>
                  <td className={styles.center}>{row.played ?? '—'}</td>
                  <td className={styles.center}>{row.wins ?? '—'}</td>
                  <td className={styles.center}>{row.losses ?? '—'}</td>
                  <td className={`${styles.center} ${styles.bold}`}>
                    {formatCompetitionNumber(row.weighted_points)}
                  </td>
                  <td className={styles.center}>{formatCompetitionNumber(row.games_for)}</td>
                  <td className={styles.center}>{formatCompetitionNumber(row.games_against)}</td>
                  <td className={styles.center}>{formatCompetitionNumber(row.games_diff)}</td>
                  <td className={styles.center}>
                    <span
                      className={styles.catCount}
                      aria-label={`${row.categories_played_count ?? 0} categorías: ${row.categories_played_list?.join(', ') || 'sin detalle'}`}
                    >
                      {row.categories_played_count ?? 0}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </section>
  );
};
