import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../api/client';
import MatchCard from '../components/MatchCard';

export default function Schedule() {
    const { categoryId } = useParams();
    const [category, setCategory] = useState(null);
    const [schedule, setSchedule] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get(`/categories/${categoryId}/schedule`)
            .then(response => {
                const data = response.data.data;
                setCategory(data);
                setSchedule(data.rounds || []);
                setLoading(false);
            })
            .catch(error => {
                console.error(error);
                setLoading(false);
            });
    }, [categoryId]);

    if (loading) return <div className="page-container"><p>Cargando calendario...</p></div>;
    if (!category) return <div className="page-container"><p>Categoría no encontrada.</p></div>;

    const translateStatus = (status) => {
        const statuses = {
             'scheduled': 'Programado',
             'submitted': 'Pendiente de Validar',
             'validated': 'Finalizado',
             'postponed': 'Aplazado',
             'cancelled': 'Cancelado'
        };
        return statuses[status] || status;
    };

    return (
        <div className="page-container">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '2rem' }}>
                <div>
                    <h1 style={{ marginBottom: '0.5rem' }}>{category.name}</h1>
                    <p style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        <span style={{ background: 'rgba(255,255,255,0.1)', padding: '4px 10px', borderRadius: '16px', fontSize: '0.85rem' }}>{category.championship?.name}</span>
                        <span style={{ color: 'var(--text-secondary)' }}>Calendario de partidos</span>
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem', background: 'rgba(0,0,0,0.2)', padding: '0.4rem', borderRadius: '8px' }}>
                    <Link to={`/categories/${categoryId}/standings`} style={{ padding: '0.5rem 1rem', borderRadius: '6px', color: 'var(--text-secondary)', textDecoration: 'none' }}>Clasificación</Link>
                    <Link to={`/categories/${categoryId}/schedule`} style={{ background: 'var(--surface-color)', padding: '0.5rem 1rem', borderRadius: '6px', color: 'var(--text-primary)', fontWeight: 'bold', textDecoration: 'none', boxShadow: 'var(--shadow-sm)' }}>Calendario & Resultados</Link>
                </div>
            </div>

            {schedule.length === 0 ? (
                <p>No hay jornadas configuradas todavía.</p>
            ) : (
                schedule.map(round => (
                    <div key={round.id} style={{ marginBottom: '3rem' }}>
                        <h2 style={{ borderBottom: '1px solid var(--surface-border)', paddingBottom: '0.5rem', marginBottom: '1.5rem', color: 'var(--brand-primary)' }}>
                            {round.name}
                        </h2>
                        
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(350px, 1fr))', gap: '1.5rem' }}>
                            {round.matches?.map(match => (
                                <MatchCard key={match.id} match={match} translateStatus={translateStatus} />
                            ))}
                            {(!round.matches || round.matches.length === 0) && (
                                <p style={{ color: 'var(--text-secondary)' }}>No hay partidos programados en esta jornada.</p>
                            )}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}
