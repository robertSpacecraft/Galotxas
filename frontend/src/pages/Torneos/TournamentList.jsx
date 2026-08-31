import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { TournamentFilters } from '../../components/Torneos/TournamentFilters';
import { TournamentCard } from '../../components/Torneos/TournamentCard';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { COMPETITION_PATH } from '../../navigation/competitionRoutes';
import styles from './Torneos.module.css';

const normalizeSeasonId = (value) => {
  if (typeof value !== 'string' || !/^\d+$/.test(value)) {
    return '';
  }

  const normalized = value.replace(/^0+/, '');

  return normalized;
};

export const TournamentList = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [tournaments, setTournaments] = useState([]);
  const [seasons, setSeasons] = useState([]);
  const [tournamentStatus, setTournamentStatus] = useState('loading');
  const [seasonStatus, setSeasonStatus] = useState('loading');
  const [localFilters, setLocalFilters] = useState({
    type: '',
    status: ''
  });
  const tournamentRequest = useRef(0);
  const seasonRequest = useRef(0);
  const hasSeasonIdParam = searchParams.has('season_id');
  const rawSeasonId = searchParams.get('season_id');
  const seasonId = normalizeSeasonId(rawSeasonId);
  const filters = useMemo(() => ({
    season_id: seasonId,
    ...localFilters,
  }), [localFilters, seasonId]);

  const setSeasonSearchParam = useCallback((nextSeasonId) => {
    const normalizedSeasonId = normalizeSeasonId(nextSeasonId);

    setSearchParams((currentParams) => {
      const nextParams = new URLSearchParams(currentParams);

      if (normalizedSeasonId) {
        nextParams.set('season_id', normalizedSeasonId);
      } else {
        nextParams.delete('season_id');
      }

      return nextParams;
    }, { replace: true });
  }, [setSearchParams]);

  const handleFilterChange = useCallback((nextFilters) => {
    const { season_id: nextSeasonId, ...nextLocalFilters } = nextFilters;

    setLocalFilters(nextLocalFilters);
    setSeasonSearchParam(nextSeasonId);
  }, [setSeasonSearchParam]);

  const loadSeasons = useCallback(async () => {
    const requestId = seasonRequest.current + 1;
    seasonRequest.current = requestId;
    setSeasonStatus('loading');

    try {
      const data = await championshipsService.getSeasons();

      if (seasonRequest.current === requestId) {
        setSeasons(Array.isArray(data) ? data : []);
        setSeasonStatus('content');
      }
    } catch {
      if (seasonRequest.current === requestId) {
        setSeasons([]);
        setSeasonStatus('error');
      }
    }
  }, []);

  const loadTournaments = useCallback(async () => {
    const requestId = tournamentRequest.current + 1;
    tournamentRequest.current = requestId;
    setTournamentStatus('loading');

    try {
      const response = await championshipsService.getChampionships(filters);

      if (tournamentRequest.current === requestId) {
        const data = Array.isArray(response) ? response : [];
        setTournaments(data);
        setTournamentStatus(data.length > 0 ? 'content' : 'empty');
      }
    } catch {
      if (tournamentRequest.current === requestId) {
        setTournaments([]);
        setTournamentStatus('error');
      }
    }
  }, [filters]);

  useEffect(() => {
    void Promise.resolve().then(loadSeasons);

    return () => {
      seasonRequest.current += 1;
    };
  }, [loadSeasons]);

  useEffect(() => {
    void Promise.resolve().then(loadTournaments);

    return () => {
      tournamentRequest.current += 1;
    };
  }, [loadTournaments]);

  useEffect(() => {
    if (hasSeasonIdParam && (!seasonId || rawSeasonId !== seasonId)) {
      setSeasonSearchParam(seasonId);
    }
  }, [hasSeasonIdParam, rawSeasonId, seasonId, setSeasonSearchParam]);

  useEffect(() => {
    if (
      seasonStatus === 'content'
      && seasonId
      && !seasons.some((season) => String(season.id) === seasonId)
    ) {
      setSeasonSearchParam('');
    }
  }, [seasonId, seasonStatus, seasons, setSeasonSearchParam]);

  return (
    <div className={styles.container}>
      <PageMetadata
        title="Campeonatos"
        description="Consulta los campeonatos públicos de Galotxas y accede a sus categorías, clasificaciones y calendarios."
      />
      <Link to={COMPETITION_PATH} className={styles.backLink}>
        ← Volver a Competición
      </Link>
      <header className={styles.listHeader}>
        <h1 className={styles.title}>Campeonatos</h1>
        <p className={styles.subtitle}>
          Explora todos los campeonatos públicos de Galotxas y entra en sus categorías.
        </p>
      </header>

      {seasonStatus === 'error' ? (
        <div className={styles.contextMessage} role="status">
          <p>No se han podido cargar las temporadas. Puedes seguir usando los demás filtros.</p>
          <button type="button" className={styles.secondaryRetry} onClick={loadSeasons}>
            Reintentar temporadas
          </button>
        </div>
      ) : null}

      <TournamentFilters
        seasons={seasons}
        currentFilters={filters}
        onFilterChange={handleFilterChange}
      />

      {tournamentStatus === 'loading' ? (
        <p className={styles.loading} role="status">Cargando campeonatos…</p>
      ) : null}
      {tournamentStatus === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>No se han podido cargar los campeonatos.</p>
          <button type="button" className={styles.retryButton} onClick={loadTournaments}>
            Reintentar
          </button>
        </div>
      ) : null}
      {tournamentStatus === 'content' ? (
        <div className={styles.grid}>
          {tournaments.map((tournament) => (
            <TournamentCard key={tournament.id} tournament={tournament} />
          ))}
        </div>
      ) : null}
      {tournamentStatus === 'empty' ? (
        <p className={styles.noResults}>No hay campeonatos para los filtros seleccionados.</p>
      ) : null}
    </div>
  );
};
