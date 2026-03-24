import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../api/client';
import styles from './Standings.module.css';

export default function Standings() {
    const { categoryId } = useParams();
    const [category, setCategory] = useState(null);
    const [standings, setStandings] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            api.get(`/categories/${categoryId}`),
            api.get(`/categories/${categoryId}/standings`)
        ]).then(([catRes, stdRes]) => {
            setCategory(catRes.data.data);
            setStandings(stdRes.data.data);
            setLoading(false);
        }).catch(error => {
            console.error(error);
            setLoading(false);
        });
    }, [categoryId]);

    if (loading) return <div className="page-container"><p>Cargando clasificación...</p></div>;
    if (!category) return <div className="page-container"><p>Categoría no encontrada.</p></div>;

    return (
        <div className="page-container">
            <div className={styles.header}>
                <div>
                    <h1 className={styles.title}>{category.name}</h1>
                    <div className={styles.meta}>
                        <span className={styles.badge}>{category.championship?.name}</span>
                        <span className={styles.metaText}>•</span>
                        <span className={styles.metaText}>{category.championship?.season?.name}</span>
                    </div>
                </div>
                <div className={styles.nav}>
                    <Link to={`/categories/${categoryId}/standings`} className={`${styles.navLink} ${styles.navLinkActive}`}>Clasificación</Link>
                    <Link to={`/categories/${categoryId}/schedule`} className={styles.navLink}>Calendario & Resultados</Link>
                </div>
            </div>

            <div className={styles.tableWrapper}>
                <table className={styles.table}>
                    <thead>
                        <tr className={styles.headerRow}>
                            <th className={styles.pos}>#</th>
                            <th>Participante</th>
                            <th className={styles.center}>PJ</th>
                            <th className={styles.center}>V</th>
                            <th className={styles.center}>D</th>
                            <th className={styles.center}>JF</th>
                            <th className={styles.center}>JC</th>
                            <th className={styles.center}>Dif</th>
                            <th className={styles.center}>PTS</th>
                        </tr>
                    </thead>
                    <tbody>
                        {standings.map((row, index) => (
                            <tr key={row.entry_id} className={styles.row}>
                                <td className={`${styles.pos} ${index < 4 ? styles.posTop : styles.posNormal}`}>{index + 1}</td>
                                <td className={styles.name}>{row.name}</td>
                                <td className={styles.center}>{row.matches_played}</td>
                                <td className={`${styles.center} var-success`}>{row.wins}</td>
                                <td className={`${styles.center} var-danger`}>{row.losses}</td>
                                <td className={styles.center}>{row.games_for}</td>
                                <td className={styles.center}>{row.games_against}</td>
                                <td className={`${styles.center} ${row.games_diff > 0 ? 'var-success' : (row.games_diff < 0 ? 'var-danger' : '')}`}>
                                    {row.games_diff > 0 ? `+${row.games_diff}` : row.games_diff}
                                </td>
                                <td className={styles.points}>{row.points}</td>
                            </tr>
                        ))}
                        {standings.length === 0 && (
                            <tr>
                                <td colSpan="9" className={styles.emptyMessage}>No hay participantes o resultados todavía.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
