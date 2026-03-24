import React, { useState, useEffect } from 'react';
import { championshipsService } from '../../api/championships';
import { AllTimeRanking } from '../../components/Rankings/AllTimeRanking';
import { SeasonRanking } from '../../components/Rankings/SeasonRanking';
import styles from './Rankings.module.css';

export const Rankings = () => {
    const [seasons, setSeasons] = useState([]);
    const [selectedSeasonId, setSelectedSeasonId] = useState('');
    const [activeTab, setActiveTab] = useState('all-time');
    const [loadingSeasons, setLoadingSeasons] = useState(true);

    useEffect(() => {
        const fetchSeasons = async () => {
            try {
                const data = await championshipsService.getSeasons();
                setSeasons(data || []);
                
                // Set the most recent season by default (assuming sorted by ID or date descending)
                if (data && data.length > 0) {
                    // Sorting by ID descending to get the latest (usually higher ID is newer)
                    const sorted = [...data].sort((a, b) => b.id - a.id);
                    setSelectedSeasonId(sorted[0].id);
                }
            } catch (err) {
                console.error('Error fetching seasons:', err);
            } finally {
                setLoadingSeasons(false);
            }
        };
        fetchSeasons();
    }, []);

    return (
        <div className={styles.container}>
            <header className={styles.header}>
                <h1 className={styles.title}>Rankings Galotxas</h1>
                <p className={styles.subtitle}>
                    Explora el rendimiento de los mejores jugadores en toda la historia y por temporadas.
                </p>
            </header>

            <div className={styles.tabs}>
                <button 
                    className={`${styles.tabBtn} ${activeTab === 'all-time' ? styles.activeTab : ''}`}
                    onClick={() => setActiveTab('all-time')}
                >
                    Ranking Histórico
                </button>
                <button 
                    className={`${styles.tabBtn} ${activeTab === 'season' ? styles.activeTab : ''}`}
                    onClick={() => setActiveTab('season')}
                >
                    Ranking de Temporada
                </button>
            </div>

            <div className={styles.content}>
                {activeTab === 'all-time' && (
                    <AllTimeRanking />
                )}
                
                {activeTab === 'season' && (
                    <SeasonRanking 
                        seasons={seasons} 
                        selectedSeasonId={selectedSeasonId}
                        onSeasonChange={setSelectedSeasonId}
                        loadingSeasons={loadingSeasons}
                    />
                )}
            </div>
        </div>
    );
};
