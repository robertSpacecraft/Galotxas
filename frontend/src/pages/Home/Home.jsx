import { Link } from 'react-router-dom';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import {
  getPublicNavigationChild,
  getPublicNavigationItem,
} from '../../navigation/publicNavigation';
import styles from './Home.module.css';

const homeDestinations = Object.freeze({
  competition: getPublicNavigationItem('competition'),
  learn: getPublicNavigationChild('learn', 'learn-overview'),
  manual: getPublicNavigationChild('learn', 'manual'),
  school: getPublicNavigationChild('learn', 'school'),
  clubAbout: getPublicNavigationChild('club', 'club-about'),
  clubContact: getPublicNavigationChild('club', 'club-contact'),
});

export const Home = () => (
  <>
    <PageMetadata
      title="Club Galotxes Monòver"
      description="Consulta las competiciones, aprende las reglas y conoce la Escuela de Galotxas y la actividad del Club Galotxes Monòver."
    />

    <section className={styles.hero} aria-labelledby="home-title">
      <div className={styles.heroContent}>
        <h1 id="home-title" className={styles.title}>Galotxas en Monóvar</h1>
        <p className={styles.introduction}>
          Consulta las competiciones, aprende las reglas y conoce la Escuela de Galotxas y la
          actividad del Club Galotxes Monòver.
        </p>
        <div className={styles.heroActions}>
          <Link className={styles.primaryAction} to={homeDestinations.competition.to}>
            Ver competición
          </Link>
          <Link className={styles.secondaryAction} to={homeDestinations.learn.to}>
            Aprender a jugar
          </Link>
        </div>
      </div>
    </section>

    <div className={styles.journeys}>
      <section className={styles.card} aria-labelledby="home-competition-title">
        <h2 id="home-competition-title">Competición</h2>
        <p>Consulta campeonatos, categorías, clasificaciones, calendarios y resultados.</p>
        <div className={styles.cardActions}>
          <Link to={homeDestinations.competition.to}>Ver competición</Link>
        </div>
      </section>

      <section className={styles.card} aria-labelledby="home-learn-title">
        <h2 id="home-learn-title">Aprende a jugar</h2>
        <p>Conoce cómo se juega y consulta el Manual público de las Galotxas.</p>
        <div className={styles.cardActions}>
          <Link to={homeDestinations.learn.to}>Aprende a jugar</Link>
          <Link to={homeDestinations.manual.to}>Manual y reglas</Link>
        </div>
      </section>

      <section className={styles.card} aria-labelledby="home-school-title">
        <h2 id="home-school-title">Escuela de Galotxas</h2>
        <p>
          Consulta el programa, los niveles, los horarios publicados y el estado de las
          inscripciones.
        </p>
        <div className={styles.cardActions}>
          <Link to={homeDestinations.school.to}>Ver Escuela</Link>
        </div>
      </section>

      <section className={styles.card} aria-labelledby="home-club-title">
        <h2 id="home-club-title">Club Galotxes Monòver</h2>
        <p>Conoce la entidad, su actividad y sus canales oficiales de contacto.</p>
        <div className={styles.cardActions}>
          <Link to={homeDestinations.clubAbout.to}>Quiénes somos</Link>
          <Link to={homeDestinations.clubContact.to}>Contacto</Link>
        </div>
      </section>
    </div>
  </>
);
