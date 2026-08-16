import { SponsorLogo } from './SponsorLogo';
import { useSponsors } from './useSponsors';
import styles from './SponsorStrip.module.css';

export const SponsorStrip = () => {
  const { sponsors, status } = useSponsors();

  if (status !== 'content') return null;

  return (
    <section className={styles.section} aria-labelledby="sponsors-title">
      <div className={styles.content}>
        <h2 id="sponsors-title" className={styles.title}>Colaboradores</h2>
        <ul className={styles.grid}>
          {sponsors.map((sponsor) => (
            <SponsorLogo key={sponsor.id} sponsor={sponsor} />
          ))}
        </ul>
      </div>
    </section>
  );
};
