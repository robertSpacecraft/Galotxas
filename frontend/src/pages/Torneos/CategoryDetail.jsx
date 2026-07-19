import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { championshipsService } from '../../api/championships';
import { CategoryNavigation } from '../../components/Competition/CategoryNavigation';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import {
  getChampionshipDetailPath,
  TOURNAMENTS_PATH,
} from '../../navigation/competitionRoutes';
import {
  getCategoryGenderLabel,
  getCategoryLevelLabel,
  getCategoryStatusLabel,
  getChampionshipTypeLabel,
} from '../Competition/competitionPresentation';
import styles from './Torneos.module.css';

export const CategoryDetail = () => {
  const { categoryId } = useParams();
  const request = useRef(0);
  const [category, setCategory] = useState(null);
  const [status, setStatus] = useState('loading');

  const loadCategory = useCallback(async () => {
    const requestId = request.current + 1;
    request.current = requestId;
    setStatus('loading');

    try {
      const data = await championshipsService.getCategory(categoryId);

      if (request.current === requestId) {
        setCategory(data || null);
        setStatus(data ? 'content' : 'error');
      }
    } catch {
      if (request.current === requestId) {
        setCategory(null);
        setStatus('error');
      }
    }
  }, [categoryId]);

  useEffect(() => {
    void Promise.resolve().then(loadCategory);

    return () => {
      request.current += 1;
    };
  }, [loadCategory]);

  if (status !== 'content') {
    return (
      <div className={styles.container}>
        <PageMetadata
          title="Categoría | Competición | Galotxas"
          description="Consulta el resumen de una categoría pública de Galotxas."
        />
        <Link to={TOURNAMENTS_PATH} className={styles.backLink}>← Volver a Torneos</Link>
        <h1 className={styles.title}>Detalle de la categoría</h1>
        {status === 'loading' ? (
          <p className={styles.loading} role="status">Cargando categoría…</p>
        ) : (
          <div className={styles.errorState} role="alert">
            <p>No se ha podido cargar la categoría.</p>
            <button type="button" className={styles.retryButton} onClick={loadCategory}>
              Reintentar
            </button>
          </div>
        )}
      </div>
    );
  }

  const championship = category.championship;
  const championshipPath = getChampionshipDetailPath(
    championship?.id || category.championship_id,
  );
  const seasonName = championship?.season?.name;

  return (
    <div className={styles.container}>
      <PageMetadata
        title={`${category.name} | Competición | Galotxas`}
        description={`Consulta el resumen, la clasificación y el calendario de ${category.name}.`}
      />
      {championshipPath ? (
        <Link to={championshipPath} className={styles.backLink}>
          ← Volver al campeonato
        </Link>
      ) : null}

      <header className={styles.detailHeader}>
        <div className={styles.headerInfo}>
          <p className={styles.contextPath}>
            {seasonName ? `${seasonName} · ` : ''}
            {championship?.name || 'Campeonato no disponible'}
          </p>
          <h1 className={styles.detailTitle}>{category.name}</h1>
        </div>
      </header>

      <CategoryNavigation categoryId={categoryId} currentView="detail" />

      <section className={styles.categorySummary} aria-labelledby="category-summary-title">
        <h2 id="category-summary-title" className={styles.subTitle}>Datos de la categoría</h2>
        <dl className={styles.summaryDetails}>
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
          <div>
            <dt>Modalidad del campeonato</dt>
            <dd>{getChampionshipTypeLabel(championship?.type)}</dd>
          </div>
        </dl>
      </section>
    </div>
  );
};
