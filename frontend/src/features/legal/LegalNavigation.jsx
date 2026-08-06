import { Link, useLocation } from 'react-router-dom';
import { legalPages } from './legalRoutes';
import styles from './LegalPage.module.css';

export const LegalNavigation = () => {
  const location = useLocation();

  return (
    <nav className={styles.navigation} aria-label="Información legal">
      <ul>
        {Object.values(legalPages).map((page) => (
          <li key={page.id}>
            <Link
              to={page.path}
              aria-current={location.pathname === page.path ? 'page' : undefined}
            >
              {page.label}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
};
