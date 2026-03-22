import galotxasHero from '../../assets/galotxas_hero.png';
import styles from './Hero.module.css';

export const Hero = () => {
  return (
    <section className={styles.hero}>
      <img 
        src={galotxasHero} 
        alt="Galotxas Hero" 
        className={styles.bgImage} 
      />
      <div className={styles.overlay}></div>
      <div className={styles.heroContent}>
        <h1 className={styles.title}>La emoción de las Galotxas</h1>
        <p className={styles.subtitle}>Sigue los torneos oficiales y descubre la pasión de la Pilota Valenciana.</p>
        <button className={styles.ctaButton}>Ver Torneos</button>
      </div>
    </section>
  );
};
