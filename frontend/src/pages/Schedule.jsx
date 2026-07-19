import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { championshipsService } from '../api/championships';
import MatchCard from '../components/MatchCard';
import {
    getCategorySchedulePath,
    getCategoryStandingsPath,
} from '../navigation/competitionRoutes';
import styles from './Schedule.module.css';

export default function Schedule() {
    const { categoryId } = useParams();
    const [category, setCategory] = useState(null);
    const [schedule, setSchedule] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [contextError, setContextError] = useState(false);

    useEffect(() => {
        let active = true;

        const loadSchedule = async () => {
            setLoading(true);
            setError(null);
            setContextError(false);

            const [categoryResult, scheduleResult] = await Promise.allSettled([
                championshipsService.getCategory(categoryId),
                championshipsService.getCategorySchedule(categoryId),
            ]);

            if (!active) return;

            if (categoryResult.status === 'fulfilled' && categoryResult.value) {
                setCategory(categoryResult.value);
            } else {
                setCategory(null);
                setContextError(true);
            }

            if (scheduleResult.status === 'fulfilled' && Array.isArray(scheduleResult.value)) {
                setSchedule(scheduleResult.value);
            } else {
                setSchedule([]);
                setError('No se ha podido cargar el calendario. Inténtalo de nuevo más tarde.');
            }

            setLoading(false);
        };

        loadSchedule();

        return () => {
            active = false;
        };
    }, [categoryId]);

    if (loading) return <div className="page-container"><p role="status">Cargando calendario...</p></div>;

    if (error) {
        return (
            <div className="page-container" role="alert">
                <h1>No se ha podido cargar el calendario</h1>
                <p>{error}</p>
            </div>
        );
    }

    const translateStatus = (status) => {
        const statuses = {
             'scheduled': 'Programado',
             'submitted': 'Pendiente de Validar',
             'validated': 'Finalizado',
             'postponed': 'Aplazado',
             'cancelled': 'Cancelado'
        };
        return statuses[status] || status || 'Estado por determinar';
    };

    const categoryName = category?.name || 'Calendario de la categoría';
    const championshipName = category?.championship?.name || 'Campeonato por determinar';
    const standingsPath = getCategoryStandingsPath(categoryId);
    const schedulePath = getCategorySchedulePath(categoryId);

    return (
        <div className="page-container">
            <div className={styles.header}>
                <div>
                    <h1 className={styles.title}>{categoryName}</h1>
                    <div className={styles.meta}>
                        <span className={styles.badge}>{championshipName}</span>
                        <span className={styles.metaText}>Calendario de partidos</span>
                    </div>
                </div>
                <div className={styles.nav}>
                    <Link to={standingsPath} className={styles.navLink}>Clasificación</Link>
                    <Link to={schedulePath} className={`${styles.navLink} ${styles.navLinkActive}`}>Calendario & Resultados</Link>
                </div>
            </div>

            {contextError && (
                <p className={styles.contextWarning} role="status">
                    El calendario está disponible, pero no se ha podido cargar la información de la categoría.
                </p>
            )}

            {schedule.length === 0 ? (
                <p className={styles.emptySchedule}>No hay jornadas configuradas todavía.</p>
            ) : (
                schedule.map((round, roundIndex) => (
                    <section key={round.id || `${categoryId}-${roundIndex}`} className={styles.roundSection}>
                        <h2 className={styles.roundTitle}>
                            {round.name || `Jornada ${roundIndex + 1}`}
                        </h2>
                        
                        <div className={styles.matchesGrid}>
                            {Array.isArray(round.matches) && round.matches.map((match, matchIndex) => (
                                <MatchCard
                                    key={match.id || `${round.id || roundIndex}-${matchIndex}`}
                                    match={match}
                                    translateStatus={translateStatus}
                                    officialScoresOnly
                                    showDetailLabel
                                    showVenue
                                />
                            ))}
                            {(!Array.isArray(round.matches) || round.matches.length === 0) && (
                                <p className={styles.emptyMessage}>No hay partidos programados en esta jornada.</p>
                            )}
                        </div>
                    </section>
                ))
            )}
        </div>
    );
}
