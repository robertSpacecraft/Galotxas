import React, { useState, useEffect } from 'react';
import { useParams, Link, useLocation, useNavigate } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { TournamentRanking } from '../../components/Torneos/TournamentRanking';
import { useAuth } from '../../context/AuthContext';
import styles from './Torneos.module.css';

export const TournamentDetail = () => {
  const { championshipId } = useParams();
  const { user, isAuthenticated } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  const [tournament, setTournament] = useState(null);
  const [ranking, setRanking] = useState([]);
  const [loading, setLoading] = useState(true);
  
  const [regStatus, setRegStatus] = useState(null);
  const [regLoading, setRegLoading] = useState(false);
  const [regError, setRegError] = useState(null);
  const [regSuccess, setRegSuccess] = useState(false);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [championshipRes, rankingRes] = await Promise.all([
          championshipsService.getChampionship(championshipId),
          championshipsService.getChampionshipRanking(championshipId)
        ]);
        setTournament(championshipRes);
        setRanking(rankingRes || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    loadData();
  }, [championshipId]);

  useEffect(() => {
    if (isAuthenticated && user?.player && tournament) {
      checkRegistrationStatus();
    }
  }, [isAuthenticated, user, tournament]);

  const checkRegistrationStatus = async () => {
    try {
      const statusData = await championshipsService.getRegistrationStatus(championshipId);
      // The backend returns an object with a 'request' key. If it's null, we are not registered.
      if (statusData && statusData.request) {
        setRegStatus(statusData.request);
      } else {
        setRegStatus(null);
      }
    } catch (err) {
      if (err.response && err.response.status !== 404) {
        console.error("Error checking registration status:", err);
      }
      setRegStatus(null);
    }
  };

  const handleRegisterClick = async () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }

    if (!user?.player) {
      navigate('/player', { state: { action: 'createProfile', from: location.pathname } });
      return;
    }

    if (!window.confirm(`¿Estás seguro de que deseas enviar la solicitud de inscripción al campeonato "${tournament.name}"?`)) {
        return;
    }

    setRegLoading(true);
    setRegError(null);
    setRegSuccess(false);

    try {
      await championshipsService.registerChampionship(championshipId);
      setRegSuccess(true);
      await checkRegistrationStatus();
    } catch (err) {
      setRegError(err.response?.data?.message || 'Hubo un error al procesar tu inscripción.');
    } finally {
      setRegLoading(false);
    }
  };

  if (loading) return <div className={styles.loading}>Cargando detalle del torneo...</div>;
  if (!tournament) return <div className={styles.noResults}>Torneo no encontrado.</div>;

  const {
    name,
    description,
    type,
    status,
    start_date,
    end_date,
    registration_status,
    registration_starts_at,
    registration_ends_at,
    registration_is_open,
    season,
    categories
  } = tournament;

  return (
    <div className={styles.container}>
      <Link to="/torneos" className={styles.backLink}>← Volver al listado</Link>
      
      <header className={styles.detailHeader}>
        <div className={styles.headerInfo}>
          <span className={styles.seasonBadge}>{season?.name}</span>
          <h1 className={styles.detailTitle}>{name}</h1>
          <div className={styles.meta}>
            <span className={styles.metaItem}><strong>Tipo:</strong> {type}</span>
            <span className={styles.metaItem}><strong>Estado:</strong> {status}</span>
            <span className={styles.metaItem}>
              <strong>Fechas:</strong> {new Date(start_date).toLocaleDateString()} - {new Date(end_date).toLocaleDateString()}
            </span>
          </div>
        </div>
      </header>

      <div className={styles.detailBody}>
        <div className={styles.mainInfo}>
          <section className={styles.descriptionSection}>
            <h2 className={styles.subTitle}>Descripción</h2>
            <div className={styles.descriptionText}>{description}</div>
          </section>

          <section className={styles.categoriesSection}>
            <h2 className={styles.subTitle}>Categorías</h2>
            {categories && categories.length > 0 ? (
              <div className={styles.categoriesGrid}>
                {categories.map(cat => (
                  <div key={cat.id} className={styles.categoryCard}>
                    <h3>{cat.name}</h3>
                    <Link to={`/categories/${cat.id}`} className={styles.catLink}>Ver categoría</Link>
                  </div>
                ))}
              </div>
            ) : (
              <p>No hay categorías registradas en este torneo aún.</p>
            )}
          </section>
        </div>

        <aside className={styles.sidebar}>
          <div className={styles.registrationBox}>
            <h3 className={styles.boxTitle}>Inscripción</h3>
            <div className={styles.regStatus}>
              <strong>Estado:</strong> 
              <span className={registration_is_open ? styles.open : styles.closed}>
                {registration_status === 'open' ? 'Abierta' : 'Cerrada'}
              </span>
            </div>
            <div className={styles.regDates}>
              <p>Abierta desde: {new Date(registration_starts_at).toLocaleDateString()}</p>
              <p>Hasta: {new Date(registration_ends_at).toLocaleDateString()}</p>
            </div>
            
            <div className={styles.registrationActionArea} style={{ marginTop: '1.5rem' }}>
              {regStatus ? (
                <div className={styles.statusBox} style={{ padding: '1rem', backgroundColor: '#2a2f3a', borderRadius: '8px', border: '1px solid #4ade80' }}>
                  <h4 style={{ margin: '0 0 0.5rem 0', color: '#4ade80' }}>Estado de tu inscripción</h4>
                  <p style={{ margin: 0 }}>
                    Tu solicitud está: <strong style={{ textTransform: 'capitalize' }}>{regStatus.status || 'Registrada'}</strong>
                  </p>
                </div>
              ) : registration_is_open ? (
                <>
                  <button 
                    className={styles.mainRegisterBtn} 
                    onClick={handleRegisterClick}
                    disabled={regLoading}
                    style={{ width: '100%', cursor: regLoading ? 'not-allowed' : 'pointer' }}
                  >
                    {regLoading ? 'Procesando...' : 'Inscribirme'}
                  </button>
                  {regError && <div style={{ color: '#ef4444', marginTop: '0.5rem', fontSize: '0.9rem' }}>{regError}</div>}
                  {regSuccess && <div style={{ color: '#4ade80', marginTop: '0.5rem', fontSize: '0.9rem' }}>¡Solicitud enviada correctamente!</div>}
                </>
              ) : (
                <p style={{ fontSize: '0.9rem', color: '#aaa', textAlign: 'center' }}>Las inscripciones no están disponibles en este momento.</p>
              )}
            </div>
          </div>
        </aside>
      </div>

      <TournamentRanking ranking={ranking} />
    </div>
  );
};
