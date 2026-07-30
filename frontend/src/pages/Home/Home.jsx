import React from 'react';
import { Link } from 'react-router-dom';
import { Layout } from '../../components/Layout/Layout';
import { Hero } from '../../components/Hero/Hero';
import { schoolPath } from '../../features/school/schoolRoutes';
import styles from './Home.module.css';

export const Home = () => {
  return (
    <Layout>
      <Hero />
      <section className={styles.welcomeSection}>
        <h2 className={styles.welcomeTitle}>Bienvenidos a la Plataforma Oficial</h2>
        <p className={styles.welcomeText}>
          Explora los últimos torneos, consulta rankings históricos y mantente al día con toda la prensa y media del mundo de las Galotxas.
        </p>
      </section>
      
      <div className={styles.contentGrid}>
        <div className={styles.card}>
          <h3 className={styles.cardTitle}>Prensa & Media</h3>
          <p className={styles.cardText}>Últimas noticias y reportajes sobre las partidas más emocionantes.</p>
        </div>
        <div className={styles.card}>
          <h3 className={styles.cardTitle}>Federaciones</h3>
          <p className={styles.cardText}>Consulta los clubes y federaciones inscritas en la plataforma.</p>
        </div>
        <Link to={schoolPath()} className={`${styles.card} ${styles.cardLink}`}>
          <h3 className={styles.cardTitle}>Escuela de Galotxas</h3>
          <p className={styles.cardText}>Consulta los niveles, horarios y la disponibilidad de inscripciones.</p>
        </Link>
      </div>
    </Layout>
  );
};
