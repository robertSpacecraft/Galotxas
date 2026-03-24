import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { StandingsTable } from '../../components/Torneos/StandingsTable';
import MatchCard from '../../components/MatchCard';
import styles from './Torneos.module.css';

export const CategoryDetail = () => {
    const { categoryId } = useParams();
    const [category, setCategory] = useState(null);
    const [standings, setStandings] = useState([]);
    const [schedule, setSchedule] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('clasificacion');

    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            console.log('Cargando datos para categoryId:', categoryId);
            try {
                const [catRes, stdRes, schRes] = await Promise.all([
                    championshipsService.getCategory(categoryId),
                    championshipsService.getCategoryStandings(categoryId),
                    championshipsService.getCategorySchedule(categoryId)
                ]);
                
                console.log('Respuesta Categoría:', catRes);
                console.log('Respuesta Clasificación:', stdRes);
                console.log('Respuesta Calendario:', schRes);

                setCategory(catRes);
                setStandings(stdRes || []);
                setSchedule(schRes || []);

            } catch (err) {
                console.error('Error cargando datos de categoría:', err);
                // Si falla uno de los secundarios, al menos intentamos cargar la info básica
                try {
                const fallbackCat = await championshipsService.getCategory(categoryId);
                setCategory(fallbackCat);
                } catch (catErr) {
                    console.error('Error crítico al cargar categoría:', catErr);
                }
            } finally {
                setLoading(false);
            }
        };
        loadData();
    }, [categoryId]);

    if (loading) return <div className={styles.loading}>Cargando detalle de la categoría...</div>;
    if (!category) return <div className={styles.noResults}>Categoría no encontrada.</div>;

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

    const entryNames = {};
    standings.forEach(s => {
        if (s.entry_id) entryNames[s.entry_id] = s.name;
    });

    return (
        <div className={styles.container}>
            <Link to={`/torneos/${category.championship_id}`} className={styles.backLink}>
                ← Volver al torneo
            </Link>

            <header className={styles.detailHeader}>
                <div className={styles.headerInfo}>
                    <span className={styles.seasonBadge}>{category.championship?.name}</span>
                    <h1 className={styles.detailTitle}>{category.name}</h1>
                </div>
            </header>

            <div className={styles.tabsContainer}>
                <button 
                    className={`${styles.tabLink} ${activeTab === 'clasificacion' ? styles.activeTab : ''}`}
                    onClick={() => setActiveTab('clasificacion')}
                >
                    Clasificación
                </button>
                <button 
                    className={`${styles.tabLink} ${activeTab === 'calendario' ? styles.activeTab : ''}`}
                    onClick={() => setActiveTab('calendario')}
                >
                    Calendario & Resultados
                </button>
            </div>

            <div className={styles.tabContent}>
                {activeTab === 'clasificacion' ? (
                    <StandingsTable standings={standings} />
                ) : (
                    <div className={styles.scheduleGrid}>
                        {schedule.length === 0 ? (
                            <p className={styles.noResults}>No hay jornadas configuradas todavía.</p>
                        ) : (
                            schedule.map(round => (
                                <div key={round.id} className={styles.roundSection}>
                                    <h2 className={styles.roundTitle}>{round.name}</h2>
                                    <div className={styles.matchesGrid}>
                                        {round.matches?.map(match => (
                                            <MatchCard 
                                                key={match.id} 
                                                match={match} 
                                                entryNames={entryNames}
                                                translateStatus={translateStatus} 
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
