import { useCallback, useEffect, useRef, useState } from 'react';
import { championshipsService } from '../../api/championships';
import {
  formatCompetitionNumber,
} from '../../pages/Competition/competitionPresentation';
import { formatPercentage } from '../../utils/formatPercentage';
import styles from './RankingTables.module.css';

export const AllTimeRanking = () => {
  const request = useRef(0);
  const [ranking, setRanking] = useState([]);
  const [visibleCount, setVisibleCount] = useState(50);
  const [status, setStatus] = useState('loading');
  const [showLegend, setShowLegend] = useState(false);

  const loadRanking = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');

    try {
      const data = await championshipsService.getAllTimeRanking();

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
  }, []);

  useEffect(() => {
    void Promise.resolve().then(loadRanking);

    return () => {
      request.current += 1;
    };
  }, [loadRanking]);

  if (status === 'loading') {
    return <p className={styles.loading} role="status">Cargando ranking histórico…</p>;
  }

  if (status === 'error') {
    return (
      <div className={styles.error} role="alert">
        <p>No se ha podido cargar el ranking histórico.</p>
        <button type="button" className={styles.retryButton} onClick={loadRanking}>
          Reintentar
        </button>
      </div>
    );
  }

  if (status === 'empty') {
    return <p className={styles.noData}>Todavía no hay datos en el ranking histórico.</p>;
  }

  const visibleRanking = ranking.slice(0, visibleCount);
  const hasMore = visibleCount < ranking.length;

  return (
    <section className={styles.rankingBox} aria-labelledby="all-time-ranking-title">
      <h2 id="all-time-ranking-title" className={styles.sectionTitle}>Ranking histórico</h2>
      <div className={styles.infoBox}>
        <p>La posición oficial requiere al menos 10 partidos disputados.</p>
      </div>

      <div
        className={styles.tableWrapper}
        role="region"
        aria-label="Tabla del ranking histórico"
        tabIndex="0"
      >
        <table className={styles.table}>
          <caption className={styles.visuallyHidden}>Ranking histórico de jugadores</caption>
          <thead>
            <tr>
              <th scope="col">Pos.</th>
              <th scope="col">Jugador</th>
              <th scope="col" className={styles.center}><abbr title="Partidos jugados">PJ</abbr></th>
              <th scope="col" className={styles.center}><abbr title="Partidos ganados">PG</abbr></th>
              <th scope="col" className={styles.center}><abbr title="Partidos perdidos">PP</abbr></th>
              <th scope="col" className={styles.center}>% victorias</th>
              <th scope="col" className={styles.center}><abbr title="Partidos individuales">PJ I</abbr></th>
              <th scope="col" className={styles.center}><abbr title="Partidos de dobles">PJ D</abbr></th>
              <th scope="col" className={styles.center}>Puntos ponderados</th>
              <th scope="col" className={styles.center}>Puntos por partido</th>
              <th scope="col" className={styles.center}>Diferencia por partido</th>
              <th scope="col" className={styles.center}>Estado</th>
            </tr>
          </thead>
          <tbody>
            {visibleRanking.map((row) => {
              const isOfficial = row.official_ranking;

              return (
                <tr key={row.player_id || `${row.position}-${row.name}`}>
                  <td className={styles.positionCell}>
                    <span className={isOfficial ? styles.posNum : styles.noOfficial}>
                      {isOfficial ? row.position ?? '—' : '—'}
                    </span>
                  </td>
                  <th scope="row" className={styles.playerName}>
                    {row.name || 'Jugador no disponible'}
                  </th>
                  <td className={styles.center}>{row.played ?? '—'}</td>
                  <td className={styles.center}>{row.wins ?? '—'}</td>
                  <td className={styles.center}>{row.losses ?? '—'}</td>
                  <td className={styles.center}>{formatPercentage(row.win_rate)}</td>
                  <td className={styles.center}>{row.played_singles ?? '—'}</td>
                  <td className={styles.center}>{row.played_doubles ?? '—'}</td>
                  <td className={`${styles.center} ${styles.bold}`}>
                    {formatCompetitionNumber(row.weighted_points)}
                  </td>
                  <td className={styles.center}>
                    {formatCompetitionNumber(row.weighted_points_per_match)}
                  </td>
                  <td className={styles.center}>
                    {formatCompetitionNumber(row.games_diff_per_match)}
                  </td>
                  <td className={styles.center}>
                    {isOfficial ? (
                      <span className={styles.statusBadgeOk}>Oficial</span>
                    ) : (
                      <span
                        className={styles.statusBadgeNo}
                        aria-label={`Provisional. Faltan ${row.matches_needed_for_official_ranking ?? 0} partidos para la posición oficial`}
                      >
                        Provisional
                      </span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {hasMore ? (
        <div className={styles.loadMore}>
          <button type="button" onClick={() => setVisibleCount((count) => count + 50)}>
            Cargar más resultados
          </button>
        </div>
      ) : null}

      <div className={styles.footerInfo}>
        <button
          type="button"
          className={styles.legendToggle}
          aria-expanded={showLegend}
          aria-controls="ranking-legend"
          onClick={() => setShowLegend((visible) => !visible)}
        >
          {showLegend ? 'Ocultar leyenda' : 'Ver leyenda'}
        </button>

        {showLegend ? (
          <div id="ranking-legend" className={styles.legend}>
            <h3>Leyenda del ranking</h3>
            <ul>
              <li><strong>PJ:</strong> partidos jugados totales</li>
              <li><strong>PG:</strong> partidos ganados</li>
              <li><strong>PP:</strong> partidos perdidos</li>
              <li><strong>% victorias:</strong> porcentaje de victorias sobre partidos jugados</li>
              <li><strong>PJ I:</strong> partidos jugados en modalidad individual</li>
              <li><strong>PJ D:</strong> partidos jugados en modalidad dobles</li>
              <li><strong>Puntos ponderados:</strong> puntos finales ajustados por nivel y rol</li>
              <li><strong>Puntos por partido:</strong> media usada como primer criterio del orden oficial</li>
              <li><strong>Diferencia por partido:</strong> diferencia media de juegos</li>
              <li><strong>Estado:</strong> indica si se alcanza el mínimo para tener posición oficial</li>
            </ul>
            <p className={styles.orderNote}>
              <strong>Orden oficial:</strong> puntos ponderados por partido, porcentaje de victorias,
              diferencia de juegos por partido y puntos ponderados totales.
            </p>
          </div>
        ) : null}
      </div>
    </section>
  );
};
