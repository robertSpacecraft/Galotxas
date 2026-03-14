import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../api/client';

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
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '2rem' }}>
                <div>
                    <h1 style={{ marginBottom: '0.5rem' }}>{category.name}</h1>
                    <p style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        <span style={{ background: 'rgba(255,255,255,0.1)', padding: '4px 10px', borderRadius: '16px', fontSize: '0.85rem' }}>{category.championship?.name}</span>
                        <span style={{ color: 'var(--text-secondary)' }}>•</span>
                        <span style={{ color: 'var(--text-secondary)' }}>{category.championship?.season?.name}</span>
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem', background: 'rgba(0,0,0,0.2)', padding: '0.4rem', borderRadius: '8px' }}>
                    <Link to={`/categories/${categoryId}/standings`} style={{ background: 'var(--surface-color)', padding: '0.5rem 1rem', borderRadius: '6px', color: 'var(--text-primary)', fontWeight: 'bold', textDecoration: 'none', boxShadow: 'var(--shadow-sm)' }}>Clasificación</Link>
                    <Link to={`/categories/${categoryId}/schedule`} style={{ padding: '0.5rem 1rem', borderRadius: '6px', color: 'var(--text-secondary)', textDecoration: 'none' }}>Calendario & Resultados</Link>
                </div>
            </div>

            <div style={{ overflowX: 'auto', background: 'rgba(0,0,0,0.2)', borderRadius: '12px', border: '1px solid var(--surface-border)' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', minWidth: '800px' }}>
                    <thead>
                        <tr style={{ background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid var(--surface-border)' }}>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', width: '60px', textAlign: 'center' }}>#</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600' }}>Participante</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>PJ</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>V</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>D</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>JF</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>JC</th>
                            <th style={{ padding: '1.2rem', color: 'var(--text-secondary)', fontWeight: '600', textAlign: 'center' }}>Dif</th>
                            <th style={{ padding: '1.2rem', color: 'var(--brand-primary)', fontWeight: '700', textAlign: 'center' }}>PTS</th>
                        </tr>
                    </thead>
                    <tbody>
                        {standings.map((row, index) => (
                            <tr key={row.entry_id} style={{ borderBottom: '1px solid rgba(240,246,252,0.05)', transition: 'background 0.2s ease', cursor: 'default' }} onMouseOver={(e) => e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.02)'} onMouseOut={(e) => e.currentTarget.style.backgroundColor = 'transparent'}>
                                <td style={{ padding: '1.2rem', fontWeight: 'bold', textAlign: 'center', color: index < 4 ? 'var(--brand-primary)' : 'var(--text-secondary)' }}>{index + 1}</td>
                                <td style={{ padding: '1.2rem', fontWeight: '500' }}>{row.name}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center' }}>{row.matches_played}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center', color: 'var(--success)' }}>{row.wins}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center', color: 'var(--danger)' }}>{row.losses}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center' }}>{row.games_for}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center' }}>{row.games_against}</td>
                                <td style={{ padding: '1.2rem', textAlign: 'center', fontWeight: '500', color: row.games_diff > 0 ? 'var(--success)' : (row.games_diff < 0 ? 'var(--danger)' : 'inherit') }}>{row.games_diff > 0 ? `+${row.games_diff}` : row.games_diff}</td>
                                <td style={{ padding: '1.2rem', fontWeight: '800', textAlign: 'center', color: 'var(--brand-primary)', fontSize: '1.1rem', background: 'rgba(88,166,255,0.05)' }}>{row.points}</td>
                            </tr>
                        ))}
                        {standings.length === 0 && (
                            <tr>
                                <td colSpan="9" style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-secondary)' }}>No hay participantes o resultados todavía.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
