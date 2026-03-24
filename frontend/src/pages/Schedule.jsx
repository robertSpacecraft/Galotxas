import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../api/client';
import MatchCard from '../components/MatchCard';
import styles from './Schedule.module.css';

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
            <div className={styles.header}>
                <div>
                    <h1 className={styles.title}>{category.name}</h1>
                    <div className={styles.meta}>
                        <span className={styles.badge}>{category.championship?.name}</span>
                        <span className={styles.metaText}>Calendario de partidos</span>
                    </div>
                </div>
                <div className={styles.nav}>
                    <Link to={`/categories/${categoryId}/standings`} className={styles.navLink}>Clasificación</Link>
                    <Link to={`/categories/${categoryId}/schedule`} className={`${styles.navLink} ${styles.navLinkActive}`}>Calendario & Resultados</Link>
                </div>
            </div>

            {schedule.length === 0 ? (
                <p>No hay jornadas configuradas todavía.</p>
            ) : (
                schedule.map(round => (
                    <div key={round.id} className={styles.roundSection}>
                        <h2 className={styles.roundTitle}>
                            {round.name}
                        </h2>
                        
                        <div className={styles.matchesGrid}>
                            {round.matches?.map(match => (
                                <MatchCard key={match.id} match={match} translateStatus={translateStatus} />
                            ))}
                            {(!round.matches || round.matches.length === 0) && (
                                <p className={styles.emptyMessage}>No hay partidos programados en esta jornada.</p>
                            )}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}
