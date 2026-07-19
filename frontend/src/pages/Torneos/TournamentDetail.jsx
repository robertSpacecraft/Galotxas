import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { TournamentRanking } from '../../components/Torneos/TournamentRanking';
import { useAuth } from '../../hooks/useAuth';
import {
  getCategoryDetailPath,
  getCategorySchedulePath,
  getCategoryStandingsPath,
  TOURNAMENTS_PATH,
} from '../../navigation/competitionRoutes';
import {
  getCategoryGenderLabel,
  getCategoryLevelLabel,
  getCategoryStatusLabel,
  getChampionshipStatusLabel,
  getChampionshipTypeLabel,
  getCompetitionDateRangeLabel,
  getRegistrationRequestStatusLabel,
  getRegistrationStatusLabel,
} from '../Competition/competitionPresentation';
import styles from './Torneos.module.css';

export const TournamentDetail = () => {
  const { championshipId } = useParams();
  const { user, isAuthenticated } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const tournamentRequest = useRef(0);
  const rankingRequest = useRef(0);

  const [tournament, setTournament] = useState(null);
  const [tournamentStatus, setTournamentStatus] = useState('loading');
  const [ranking, setRanking] = useState([]);
  const [rankingStatus, setRankingStatus] = useState('loading');
  const [regStatus, setRegStatus] = useState(null);
  const [regLoading, setRegLoading] = useState(false);
  const [regError, setRegError] = useState(null);
  const [regSuccess, setRegSuccess] = useState(false);

  const loadTournament = useCallback(async () => {
    const requestId = tournamentRequest.current + 1;
    tournamentRequest.current = requestId;
    setTournamentStatus('loading');

    try {
      const data = await championshipsService.getChampionship(championshipId);

      if (tournamentRequest.current === requestId) {
        setTournament(data || null);
        setTournamentStatus(data ? 'content' : 'error');
      }
    } catch {
      if (tournamentRequest.current === requestId) {
        setTournament(null);
        setTournamentStatus('error');
      }
    }
  }, [championshipId]);

  const loadRanking = useCallback(async () => {
    const requestId = rankingRequest.current + 1;
    rankingRequest.current = requestId;
    setRankingStatus('loading');

    try {
      const data = await championshipsService.getChampionshipRanking(championshipId);

      if (rankingRequest.current === requestId) {
        const rows = Array.isArray(data) ? data : [];
        setRanking(rows);
        setRankingStatus(rows.length > 0 ? 'content' : 'empty');
      }
    } catch {
      if (rankingRequest.current === requestId) {
        setRanking([]);
        setRankingStatus('error');
      }
    }
  }, [championshipId]);

  const checkRegistrationStatus = useCallback(async () => {
    try {
      const statusData = await championshipsService.getRegistrationStatus(championshipId);
      setRegStatus(statusData?.request || null);
    } catch (error) {
      if (error.response?.status !== 404) {
        console.error('Error checking registration status:', error);
      }
      setRegStatus(null);
    }
  }, [championshipId]);

  useEffect(() => {
    loadTournament();
    loadRanking();

    return () => {
      tournamentRequest.current += 1;
      rankingRequest.current += 1;
    };
  }, [loadRanking, loadTournament]);

  useEffect(() => {
    if (isAuthenticated && user?.player && tournament) {
      checkRegistrationStatus();
    }
  }, [checkRegistrationStatus, isAuthenticated, tournament, user?.player]);

  const handleRegisterClick = async () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }

    if (!user?.player) {
      navigate('/player', { state: { action: 'createProfile', from: location.pathname } });
      return;
    }

    if (!window.confirm(`¿Quieres enviar la solicitud de inscripción al campeonato "${tournament.name}"?`)) {
      return;
    }

    setRegLoading(true);
    setRegError(null);
    setRegSuccess(false);

    try {
      await championshipsService.registerChampionship(championshipId);
      setRegSuccess(true);
      await checkRegistrationStatus();
    } catch (error) {
      setRegError(error.response?.data?.message || 'No se ha podido procesar tu inscripción.');
    } finally {
      setRegLoading(false);
    }
  };

  if (tournamentStatus !== 'content') {
    return (
      <div className={styles.container}>
        <PageMetadata
          title="Campeonato | Competición | Galotxas"
          description="Consulta el detalle de un campeonato público de Galotxas."
        />
        <Link to={TOURNAMENTS_PATH} className={styles.backLink}>← Volver a Torneos</Link>
        <h1 className={styles.title}>Detalle del campeonato</h1>
        {tournamentStatus === 'loading' ? (
          <p className={styles.loading} role="status">Cargando campeonato…</p>
        ) : (
          <div className={styles.errorState} role="alert">
            <p>No se ha podido cargar el campeonato.</p>
            <button type="button" className={styles.retryButton} onClick={loadTournament}>
              Reintentar
            </button>
          </div>
        )}
      </div>
    );
  }

  const {
    name,
    description,
    type,
    status,
    start_date: startDate,
    end_date: endDate,
    registration_status: registrationStatus,
    registration_starts_at: registrationStartsAt,
    registration_ends_at: registrationEndsAt,
    registration_is_open: registrationIsOpen,
    season,
    categories,
  } = tournament;
  const championshipDates = getCompetitionDateRangeLabel(startDate, endDate);
  const registrationDates = getCompetitionDateRangeLabel(
    registrationStartsAt,
    registrationEndsAt,
  );
  const categoryRows = Array.isArray(categories) ? categories : [];

  return (
    <div className={styles.container}>
      <PageMetadata
        title={`${name} | Competición | Galotxas`}
        description={`Consulta categorías, clasificación y datos del campeonato ${name}.`}
      />
      <Link to={TOURNAMENTS_PATH} className={styles.backLink}>← Volver a Torneos</Link>

      <header className={styles.detailHeader}>
        <div className={styles.headerInfo}>
          <p className={styles.seasonBadge}>{season?.name || 'Temporada no disponible'}</p>
          <h1 className={styles.detailTitle}>{name}</h1>
          <dl className={styles.meta}>
            <div className={styles.metaItem}>
              <dt>Modalidad</dt>
              <dd>{getChampionshipTypeLabel(type)}</dd>
            </div>
            <div className={styles.metaItem}>
              <dt>Estado</dt>
              <dd>{getChampionshipStatusLabel(status)}</dd>
            </div>
            {championshipDates ? (
              <div className={styles.metaItem}>
                <dt>Fechas</dt>
                <dd>{championshipDates}</dd>
              </div>
            ) : null}
          </dl>
        </div>
      </header>

      <div className={styles.detailBody}>
        <div className={styles.mainInfo}>
          {description ? (
            <section className={styles.descriptionSection}>
              <h2 className={styles.subTitle}>Descripción</h2>
              <p className={styles.descriptionText}>{description}</p>
            </section>
          ) : null}

          <section className={styles.categoriesSection}>
            <h2 className={styles.subTitle}>Categorías</h2>
            {categoryRows.length > 0 ? (
              <div className={styles.categoriesGrid}>
                {categoryRows.map((category) => {
                  const detailPath = getCategoryDetailPath(category.id);
                  const standingsPath = getCategoryStandingsPath(category.id);
                  const schedulePath = getCategorySchedulePath(category.id);

                  return (
                    <article key={category.id} className={styles.categoryCard}>
                      <h3>{category.name}</h3>
                      <dl className={styles.categoryMeta}>
                        <div>
                          <dt>Estado</dt>
                          <dd>{getCategoryStatusLabel(category.status)}</dd>
                        </div>
                        <div>
                          <dt>Categoría</dt>
                          <dd>{getCategoryGenderLabel(category.gender)}</dd>
                        </div>
                        <div>
                          <dt>Nivel</dt>
                          <dd>{getCategoryLevelLabel(category.level)}</dd>
                        </div>
                      </dl>
                      <nav className={styles.categoryActions} aria-label={`Opciones de ${category.name}`}>
                        {detailPath ? <Link to={detailPath} className={styles.categoryAction}>Ver categoría</Link> : null}
                        {standingsPath ? <Link to={standingsPath} className={styles.categoryAction}>Clasificación</Link> : null}
                        {schedulePath ? <Link to={schedulePath} className={styles.categoryAction}>Calendario y resultados</Link> : null}
                      </nav>
                    </article>
                  );
                })}
              </div>
            ) : (
              <p>No hay categorías disponibles en este campeonato.</p>
            )}
          </section>
        </div>

        <aside className={styles.sidebar} aria-label="Inscripción al campeonato">
          <div className={styles.registrationBox}>
            <h2 className={styles.boxTitle}>Inscripción</h2>
            <dl className={styles.registrationDetails}>
              <div className={styles.regStatus}>
                <dt>Estado</dt>
                <dd className={registrationIsOpen ? styles.open : styles.closed}>
                  {getRegistrationStatusLabel(registrationStatus)}
                </dd>
              </div>
              <div>
                <dt>Periodo</dt>
                <dd>{registrationDates || 'Sin fechas definidas'}</dd>
              </div>
            </dl>

            <div className={styles.registrationActionArea}>
              {regStatus ? (
                <div className={styles.statusBox} role="status">
                  <h3>Tu inscripción</h3>
                  <p>{getRegistrationRequestStatusLabel(regStatus.status)}</p>
                </div>
              ) : registrationIsOpen ? (
                <button
                  type="button"
                  className={styles.mainRegisterBtn}
                  onClick={handleRegisterClick}
                  disabled={regLoading}
                >
                  {regLoading ? 'Procesando…' : 'Inscribirme'}
                </button>
              ) : (
                <p className={styles.registrationUnavailable}>
                  Las inscripciones no están disponibles en este momento.
                </p>
              )}
              {regError ? <p className={styles.registrationError} role="alert">{regError}</p> : null}
              {regSuccess ? <p className={styles.registrationSuccess} role="status">Solicitud enviada correctamente.</p> : null}
            </div>
          </div>
        </aside>
      </div>

      {rankingStatus === 'loading' ? (
        <p className={styles.rankingState} role="status">Cargando ranking del campeonato…</p>
      ) : null}
      {rankingStatus === 'error' ? (
        <div className={styles.rankingState} role="alert">
          <p>No se ha podido cargar el ranking del campeonato.</p>
          <button type="button" className={styles.retryButton} onClick={loadRanking}>
            Reintentar ranking
          </button>
        </div>
      ) : null}
      {rankingStatus === 'empty' || rankingStatus === 'content' ? (
        <TournamentRanking ranking={ranking} />
      ) : null}
    </div>
  );
};
