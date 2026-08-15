import { useEffect, useRef, useState } from 'react';
import { championshipsService } from '../../api/championships';
import { CategoryRankingTable } from './CategoryRankingTable';
import styles from './RankingTables.module.css';
import { findById, firstId, keepValidId } from './rankingHierarchy';

export const CategoryRanking = ({ seasons, seasonStatus, onRetrySeasons }) => {
  const championshipRequest = useRef(0);
  const rankingRequest = useRef(0);
  const [requestedSeasonId, setRequestedSeasonId] = useState('');
  const [requestedChampionshipId, setRequestedChampionshipId] = useState('');
  const [requestedCategoryId, setRequestedCategoryId] = useState('');
  const [categories, setCategories] = useState([]);
  const [categoryStatus, setCategoryStatus] = useState('idle');
  const [ranking, setRanking] = useState([]);
  const [rankingStatus, setRankingStatus] = useState('idle');
  const [categoryRetryVersion, setCategoryRetryVersion] = useState(0);
  const [rankingRetryVersion, setRankingRetryVersion] = useState(0);

  const selectedSeasonId = keepValidId(seasons, requestedSeasonId);
  const selectedSeason = findById(seasons, selectedSeasonId);
  const championships = Array.isArray(selectedSeason?.championships)
    ? selectedSeason.championships
    : [];
  const selectedChampionshipId = keepValidId(championships, requestedChampionshipId);
  const selectedCategoryId = keepValidId(categories, requestedCategoryId);
  const selectedCategory = findById(categories, selectedCategoryId);

  useEffect(() => {
    const loadCategories = async () => {
      if (!selectedChampionshipId) {
        setCategories([]);
        setRequestedCategoryId('');
        setCategoryStatus('idle');
        return;
      }

      const requestId = championshipRequest.current + 1;
      championshipRequest.current = requestId;
      rankingRequest.current += 1;
      setCategories([]);
      setRequestedCategoryId('');
      setCategoryStatus('loading');
      setRanking([]);
      setRankingStatus('idle');

      try {
        const championship = await championshipsService.getChampionship(selectedChampionshipId);

        if (championshipRequest.current === requestId) {
          const rows = Array.isArray(championship?.categories) ? championship.categories : [];
          setCategories(rows);
          setRequestedCategoryId(firstId(rows));
          setCategoryStatus(rows.length > 0 ? 'content' : 'empty');
        }
      } catch {
        if (championshipRequest.current === requestId) {
          setCategories([]);
          setRequestedCategoryId('');
          setCategoryStatus('error');
        }
      }
    };

    void Promise.resolve().then(loadCategories);

    return () => {
      championshipRequest.current += 1;
    };
  }, [categoryRetryVersion, selectedChampionshipId]);

  useEffect(() => {
    const loadRanking = async () => {
      if (!selectedCategoryId) {
        setRanking([]);
        setRankingStatus('idle');
        return;
      }

      const requestId = rankingRequest.current + 1;
      rankingRequest.current = requestId;
      setRankingStatus('loading');

      try {
        const data = await championshipsService.getCategoryStandings(selectedCategoryId);

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
    };

    void Promise.resolve().then(loadRanking);

    return () => {
      rankingRequest.current += 1;
    };
  }, [rankingRetryVersion, selectedCategoryId]);

  const resetHierarchy = (seasonId, championshipId) => {
    championshipRequest.current += 1;
    rankingRequest.current += 1;
    setRequestedSeasonId(seasonId);
    setRequestedChampionshipId(championshipId);
    setRequestedCategoryId('');
    setCategories([]);
    setCategoryStatus(championshipId ? 'loading' : 'idle');
    setRanking([]);
    setRankingStatus('idle');
  };

  const handleSeasonChange = (event) => {
    const seasonId = event.target.value;
    const nextChampionships = findById(seasons, seasonId)?.championships ?? [];

    resetHierarchy(seasonId, firstId(nextChampionships));
  };

  const handleChampionshipChange = (event) => {
    resetHierarchy(selectedSeasonId, event.target.value);
  };

  const handleCategoryChange = (event) => {
    rankingRequest.current += 1;
    setRequestedCategoryId(event.target.value);
    setRanking([]);
    setRankingStatus('loading');
  };

  if (seasonStatus === 'loading') {
    return <p className={styles.loading} role="status">Cargando temporadas…</p>;
  }

  if (seasonStatus === 'error') {
    return (
      <div className={styles.error} role="alert">
        <p>No se han podido cargar las temporadas.</p>
        <button type="button" className={styles.retryButton} onClick={onRetrySeasons}>
          Reintentar temporadas
        </button>
      </div>
    );
  }

  if (seasonStatus === 'empty') {
    return <p className={styles.noData}>No hay temporadas disponibles para consultar categorías.</p>;
  }

  return (
    <section className={styles.rankingBox} aria-labelledby="category-ranking-title">
      <h2 id="category-ranking-title" className={styles.sectionTitle}>Clasificación de categoría</h2>
      <div className={styles.filterBar}>
        <div className={styles.filterField}>
          <label htmlFor="category-ranking-season">Temporada de la categoría</label>
          <select
            id="category-ranking-season"
            value={selectedSeasonId}
            onChange={handleSeasonChange}
            className={styles.select}
          >
            {seasons.map((season) => (
              <option key={season.id} value={season.id}>{season.name}</option>
            ))}
          </select>
        </div>
        <div className={styles.filterField}>
          <label htmlFor="category-ranking-championship">Campeonato de la categoría</label>
          <select
            id="category-ranking-championship"
            value={selectedChampionshipId}
            onChange={handleChampionshipChange}
            disabled={championships.length === 0}
            className={styles.select}
          >
            {championships.map((championship) => (
              <option key={championship.id} value={championship.id}>{championship.name}</option>
            ))}
          </select>
        </div>
        <div className={styles.filterField}>
          <label htmlFor="category-ranking-category">Categoría</label>
          <select
            id="category-ranking-category"
            value={selectedCategoryId}
            onChange={handleCategoryChange}
            disabled={categoryStatus !== 'content'}
            className={styles.select}
          >
            {categories.map((category) => (
              <option key={category.id} value={category.id}>{category.name}</option>
            ))}
          </select>
        </div>
        {categoryStatus === 'loading' ? (
          <span className={styles.inlineLoading} role="status">Cargando categorías…</span>
        ) : null}
        {rankingStatus === 'loading' ? (
          <span className={styles.inlineLoading} role="status">Actualizando clasificación…</span>
        ) : null}
      </div>

      {championships.length === 0 ? (
        <p className={styles.noData}>Esta temporada no tiene campeonatos públicos disponibles.</p>
      ) : null}
      {championships.length > 0 && categoryStatus === 'error' ? (
        <div className={styles.error} role="alert">
          <p>No se han podido cargar las categorías del campeonato.</p>
          <button
            type="button"
            className={styles.retryButton}
            onClick={() => setCategoryRetryVersion((version) => version + 1)}
          >
            Reintentar categorías
          </button>
        </div>
      ) : null}
      {championships.length > 0 && categoryStatus === 'empty' ? (
        <p className={styles.noData}>Este campeonato no tiene categorías públicas disponibles.</p>
      ) : null}
      {categoryStatus === 'content' && rankingStatus === 'error' ? (
        <div className={styles.error} role="alert">
          <p>No se ha podido cargar la clasificación de la categoría.</p>
          <button
            type="button"
            className={styles.retryButton}
            onClick={() => setRankingRetryVersion((version) => version + 1)}
          >
            Reintentar clasificación
          </button>
        </div>
      ) : null}
      {categoryStatus === 'content' && rankingStatus === 'empty' ? (
        <p className={styles.noData}>Todavía no hay datos de clasificación para esta categoría.</p>
      ) : null}
      {categoryStatus === 'content' && rankingStatus === 'content' ? (
        <CategoryRankingTable
          ranking={ranking}
          categoryName={selectedCategory?.name || 'la categoría seleccionada'}
        />
      ) : null}
    </section>
  );
};
