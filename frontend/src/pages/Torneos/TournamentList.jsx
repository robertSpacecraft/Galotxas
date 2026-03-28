import React, { useState, useEffect } from 'react';
import { championshipsService } from '../../api/championships';
import { TournamentFilters } from '../../components/Torneos/TournamentFilters';
import { TournamentCard } from '../../components/Torneos/TournamentCard';
import styles from './Torneos.module.css';

export const TournamentList = () => {
  const [tournaments, setTournaments] = useState([]);
  const [seasons, setSeasons] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    season_id: '',
    type: '',
    status: ''
  });

  useEffect(() => {
    const loadSeasons = async () => {
      try {
        const data = await championshipsService.getSeasons();
        setSeasons(data);
      } catch (err) {
        console.error(err);
      }
    };
    loadSeasons();
  }, []);

  useEffect(() => {
    const loadTournaments = async () => {
      setLoading(true);
      try {
        const response = await championshipsService.getChampionships(filters);
        // Service now returns the array directly
        setTournaments(response || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    loadTournaments();
  }, [filters]);

  return (
    <div className={styles.container}>
      <header className={styles.listHeader}>
        <h1 className={styles.title}>Torneos</h1>
        <p className={styles.subtitle}>
          Explora los campeonatos oficiales de Galotxas y sigue la emoción de la competición.
        </p>
      </header>

      <TournamentFilters 
        seasons={seasons} 
        currentFilters={filters} 
        onFilterChange={setFilters} 
      />

      {loading ? (
        <div className={styles.loading}>Cargando torneos...</div>
      ) : tournaments.length > 0 ? (
        <div className={styles.grid}>
          {tournaments.map(tournament => (
            <TournamentCard key={tournament.id} tournament={tournament} />
          ))}
        </div>
      ) : (
        <div className={styles.noResults}>No se encontraron torneos con los filtros seleccionados.</div>
      )}
    </div>
  );
};
