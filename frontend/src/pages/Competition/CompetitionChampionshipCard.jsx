import { Link } from 'react-router-dom';
import {
  getChampionshipDetailPath,
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

  return (
    <article className={styles.championshipCard} aria-labelledby={titleId}>
      {typeLabel ? <p className={styles.championshipType}>{typeLabel}</p> : null}
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
      <Link
        to={getChampionshipDetailPath(championship.id)}
        className={styles.championshipLink}
      >
        Ver detalle de {championship.name}
      </Link>
    </article>
  );
};
