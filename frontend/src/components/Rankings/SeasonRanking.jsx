import React, { useState, useEffect } from 'react';
import { championshipsService } from '../../api/championships';
import styles from './RankingTables.module.css';

export const SeasonRanking = ({ seasons, selectedSeasonId, onSeasonChange, loadingSeasons }) => {
    const [ranking, setRanking] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!selectedSeasonId) return;

        const fetchRanking = async () => {
            setLoading(true);
            setError(null);
            try {
                const data = await championshipsService.getSeasonRanking(selectedSeasonId);
                setRanking(data || []);
            } catch (err) {
                console.error('Error fetching season ranking:', err);
                setError('No se pudo cargar el ranking de la temporada.');
            } finally {
                setLoading(false);
            }
        };
        fetchRanking();
    }, [selectedSeasonId]);

    const getMedal = (pos) => {
        if (pos === 1) return '🥇';
        if (pos === 2) return '🥈';
        if (pos === 3) return '🥉';
        return null;
    };

    return (
        <div className={styles.rankingBox}>
            <div className={styles.filterBar}>
                <label htmlFor="season-select">Temporada:</label>
                <select 
                    id="season-select"
                    value={selectedSeasonId} 
                    onChange={(e) => onSeasonChange(e.target.value)}
                    disabled={loadingSeasons || loading}
                    className={styles.select}
                >
                    {seasons.map(s => (
                        <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                </select>
                {loading && <span className={styles.inlineLoading}>Actualizando...</span>}
            </div>

            {loading && !ranking.length ? (
                <div className={styles.loading}>Cargando ranking de temporada...</div>
            ) : error ? (
                <div className={styles.error}>{error}</div>
            ) : !ranking || ranking.length === 0 ? (
                <div className={styles.noData}>Aún no hay datos para esta temporada.</div>
            ) : (
                <div className={styles.tableWrapper}>
                    <table className={styles.table}>
                        <thead>
                            <tr>
                                <th>Pos</th>
                                <th>Jugador</th>
                                <th className={styles.center}>PJ</th>
                                <th className={styles.center}>PG</th>
                                <th className={styles.center}>PP</th>
                                <th className={styles.center}>Pts. Pond.</th>
                                <th className={styles.center}>JF</th>
                                <th className={styles.center}>JC</th>
                                <th className={styles.center}>Dif.</th>
                                <th className={styles.center}>Categorías</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ranking.map((row, index) => {
                                const medal = getMedal(index + 1);
                                return (
                                    <tr key={row.player_id || index} className={index < 3 ? styles.topThree : ''}>
                                        <td className={styles.positionCell}>
                                            <span className={styles.posNum}>
                                                {medal || (index + 1)}
                                            </span>
                                        </td>
                                        <td className={styles.playerName}>
                                            {row.name || row.player?.name}
                                        </td>
                                        <td className={styles.center}>{row.played}</td>
                                        <td className={styles.center}>{row.wins}</td>
                                        <td className={styles.center}>{row.losses}</td>
                                        <td className={`${styles.center} ${styles.bold}`}>
                                            {parseFloat(row.weighted_points).toFixed(2).replace('.', ',')}
                                        </td>
                                        <td className={styles.center}>{parseFloat(row.games_for).toFixed(2).replace('.', ',')}</td>
                                        <td className={styles.center}>{parseFloat(row.games_against).toFixed(2).replace('.', ',')}</td>
                                        <td className={`${styles.center} ${row.games_diff > 0 ? styles.positive : (row.games_diff < 0 ? styles.negative : '')}`}>
                                            {row.games_diff > 0 ? `+${parseFloat(row.games_diff).toFixed(2).replace('.', ',')}` : parseFloat(row.games_diff).toFixed(2).replace('.', ',')}
                                        </td>
                                        <td className={styles.center}>
                                            <span className={styles.catCount} title={row.categories_played_list?.join(', ')}>
                                                {row.categories_played_count}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};
