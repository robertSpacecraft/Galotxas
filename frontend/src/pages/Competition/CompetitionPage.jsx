import { Link } from 'react-router-dom';
import styles from './CompetitionPage.module.css';

export const CompetitionPage = () => (
  <div className={styles.container}>
    <header className={styles.header}>
      <h1 className={styles.title}>Competición</h1>
      <p className={styles.introduction}>
        Consulta campeonatos, categorías, calendarios, resultados y clasificaciones de Galotxas.
      </p>
    </header>

    <nav className={styles.destinations} aria-label="Opciones de competición">
      <Link to="/torneos" className={styles.card}>
        <span className={styles.cardTitle}>Torneos</span>
        <span className={styles.cardDescription}>
          Explora los campeonatos y accede a sus categorías, calendarios y resultados.
        </span>
      </Link>

      <Link to="/rankings" className={styles.card}>
        <span className={styles.cardTitle}>Rankings</span>
        <span className={styles.cardDescription}>
          Revisa la clasificación histórica y el rendimiento por temporadas.
        </span>
      </Link>
    </nav>
  </div>
);
