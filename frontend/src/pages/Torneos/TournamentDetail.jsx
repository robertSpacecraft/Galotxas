import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { TournamentRanking } from '../../components/Torneos/TournamentRanking';
import styles from './Torneos.module.css';

export const TournamentDetail = () => {
  const { championshipId } = useParams();
  const [tournament, setTournament] = useState(null);
  const [ranking, setRanking] = useState([]);
  const [loading, setLoading] = useState(true);

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
            {registration_is_open && (
              <button className={styles.mainRegisterBtn} disabled>Acceder a inscripción</button>
            )}
          </div>
        </aside>
      </div>

      <TournamentRanking ranking={ranking} />
    </div>
  );
};
