import React, { useState, useEffect } from 'react';
import { championshipsService } from '../../api/championships';
import styles from './RankingTables.module.css';

export const AllTimeRanking = () => {
    const [ranking, setRanking] = useState([]);
    const [visibleCount, setVisibleCount] = useState(50);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showLegend, setShowLegend] = useState(false);

    useEffect(() => {
        const fetchRanking = async () => {
            try {
                const data = await championshipsService.getAllTimeRanking();
                setRanking(data || []);
            } catch (err) {
                console.error('Error fetching all-time ranking:', err);
                setError('No se pudo cargar el ranking histórico.');
            } finally {
                setLoading(false);
            }
        };
        fetchRanking();
    }, []);

    if (loading) return <div className={styles.loading}>Cargando ranking histórico...</div>;
    if (error) return <div className={styles.error}>{error}</div>;
    if (!ranking || ranking.length === 0) return <div className={styles.noData}>No hay datos históricos disponibles.</div>;

    const visibleRanking = ranking.slice(0, visibleCount);
    const hasMore = visibleCount < ranking.length;

    const getMedal = (pos) => {
        if (pos === 1) return '🥇';
        if (pos === 2) return '🥈';
        if (pos === 3) return '🥉';
        return null;
    };

    return (
        <div className={styles.rankingBox}>
            <div className={styles.infoBox}>
                <p>💡 Para obtener posición oficial en el ranking debes haber jugado al menos 10 partidos.</p>
            </div>

            <div className={styles.tableWrapper}>
                <table className={styles.table}>
                    <thead>
                        <tr>
                            <th>Pos</th>
                            <th>Jugador</th>
                            <th className={styles.center}>PJ</th>
                            <th className={styles.center}>PG</th>
                            <th className={styles.center}>PP</th>
                            <th className={styles.center}>% Vic.</th>
                            <th className={styles.center}>PJ S</th>
                            <th className={styles.center}>PJ D</th>
                            <th className={styles.center}>Pts. Pond.</th>
                            <th className={styles.center}>Pnd/Part</th>
                            <th className={styles.center}>Dif/Part</th>
                            <th className={styles.center}>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRanking.map((row, index) => {
                            const medal = getMedal(index + 1);
                            const isOfficial = row.official_ranking;
                            
                            return (
                                <tr key={row.player_id || index} className={index < 3 ? styles.topThree : ''}>
                                    <td className={styles.positionCell}>
                                        {isOfficial ? (
                                            <span className={styles.posNum}>
                                                {medal || (index + 1)}
                                            </span>
                                        ) : (
                                            <span className={styles.noOfficial} title="Sin puesto oficial">
                                                -
                                            </span>
                                        )}
                                    </td>
                                    <td className={styles.playerName}>
                                        {row.name || row.player?.name}
                                    </td>
                                    <td className={styles.center}>{row.played}</td>
                                    <td className={styles.center}>{row.wins}</td>
                                    <td className={styles.center}>{row.losses}</td>
                                    <td className={styles.center}>
                                        {(row.win_rate * 100).toFixed(1)}%
                                    </td>
                                    <td className={styles.center}>{row.played_singles}</td>
                                    <td className={styles.center}>{row.played_doubles}</td>
                                    <td className={`${styles.center} ${styles.bold}`}>
                                        {parseFloat(row.weighted_points).toFixed(2).replace('.', ',')}
                                    </td>
                                    <td className={styles.center}>
                                        {parseFloat(row.weighted_points_per_match).toFixed(2).replace('.', ',')}
                                    </td>
                                    <td className={styles.center}>
                                        {parseFloat(row.games_diff_per_match).toFixed(2).replace('.', ',')}
                                    </td>
                                    <td className={styles.center}>
                                        {isOfficial ? (
                                            <span className={styles.statusBadgeOk}>Oficial</span>
                                        ) : (
                                            <span className={styles.statusBadgeNo} title={`Faltan ${row.matches_needed_for_official_ranking} partidos`}>
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

            {hasMore && (
                <div className={styles.loadMore}>
                    <button onClick={() => setVisibleCount(prev => prev + 50)}>
                        Cargar más resultados
                    </button>
                </div>
            )}

            <div className={styles.footerInfo}>
                <button 
                    className={styles.legendToggle} 
                    onClick={() => setShowLegend(!showLegend)}
                >
                    {showLegend ? 'Ocultar leyenda' : 'Ver leyenda'}
                </button>
                
                {showLegend && (
                    <div className={styles.legend}>
                        <h4>Leyenda de Ranking</h4>
                        <ul>
                            <li><strong>PJ:</strong> Partidos jugados totales</li>
                            <li><strong>PG:</strong> Partidos ganados</li>
                            <li><strong>PP:</strong> Partidos perdidos</li>
                            <li><strong>% Victorias:</strong> Porcentaje de victorias sobre partidos jugados</li>
                            <li><strong>PJ S:</strong> Partidos jugados en modalidad Singles</li>
                            <li><strong>PJ D:</strong> Partidos jugados en modalidad Dobles</li>
                            <li><strong>Pts. Pond.:</strong> Puntos finales ajustados por nivel y rol</li>
                            <li><strong>Pnd/Part:</strong> Media de puntos ponderados por partido (Orden oficial principal)</li>
                            <li><strong>Dif/Part:</strong> Diferencia de juegos media por partido</li>
                            <li><strong>Estado:</strong> Indica si el jugador cumple el mínimo de partidos (10) para posición oficial</li>
                        </ul>
                        <p className={styles.orderNote}>
                            <strong>Orden oficial:</strong> Puntos ponderados por partido, % de victorias, diferencia de juegos por partido y puntos ponderados totales.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};
