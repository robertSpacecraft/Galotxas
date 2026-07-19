import { Link } from 'react-router-dom';
import { getChampionshipDetailPath } from '../../navigation/competitionRoutes';
import {
  getChampionshipStatusLabel,
  getChampionshipTypeLabel,
} from './competitionPresentation';
import styles from './CompetitionPage.module.css';

const getCategoriesLabel = (categoriesCount) => {
  if (!Number.isInteger(categoriesCount) || categoriesCount < 0) {
    return null;
  }

  return categoriesCount === 1 ? '1 categoría' : `${categoriesCount} categorías`;
};

export const CompetitionChampionshipCard = ({ championship }) => {
  const titleId = `competition-championship-${championship.id}-title`;
  const typeLabel = getChampionshipTypeLabel(championship.type);
  const categoriesLabel = getCategoriesLabel(championship.categories_count);
  const detailPath = getChampionshipDetailPath(championship.id);

  return (
    <article className={styles.championshipCard} aria-labelledby={titleId}>
      <p className={styles.championshipType}>{typeLabel}</p>
      <h4 id={titleId} className={styles.championshipTitle}>{championship.name}</h4>
      <dl className={styles.details}>
        <div className={styles.detail}>
          <dt>Estado</dt>
          <dd>{getChampionshipStatusLabel(championship.status)}</dd>
        </div>
        {categoriesLabel ? (
          <div className={styles.detail}>
            <dt>Categorías</dt>
            <dd>{categoriesLabel}</dd>
          </div>
        ) : null}
      </dl>
      {detailPath ? (
        <Link to={detailPath} className={styles.championshipLink}>
          Ver detalle de {championship.name}
        </Link>
      ) : null}
    </article>
  );
};
