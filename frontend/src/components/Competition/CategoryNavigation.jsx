import { Link } from 'react-router-dom';
import {
  getCategoryDetailPath,
  getCategorySchedulePath,
  getCategoryStandingsPath,
} from '../../navigation/competitionRoutes';
import styles from './CategoryNavigation.module.css';

const buildItems = (categoryId) => [
  {
    id: 'detail',
    label: 'Resumen',
    to: getCategoryDetailPath(categoryId),
  },
  {
    id: 'standings',
    label: 'Clasificación',
    to: getCategoryStandingsPath(categoryId),
  },
  {
    id: 'schedule',
    label: 'Calendario y resultados',
    to: getCategorySchedulePath(categoryId),
  },
];

export const CategoryNavigation = ({ categoryId, currentView }) => (
  <nav className={styles.navigation} aria-label="Vistas de la categoría">
    {buildItems(categoryId).map((item) => (
      item.to ? (
        <Link
          key={item.id}
          to={item.to}
          className={`${styles.link} ${currentView === item.id ? styles.current : ''}`}
          aria-current={currentView === item.id ? 'page' : undefined}
        >
          {item.label}
        </Link>
      ) : null
    ))}
  </nav>
);
