import styles from './PublicLanding.module.css';

export const LandingSection = ({ id, title, introduction = null, children }) => {
  const titleId = `${id}-title`;
  const introductionId = introduction ? `${id}-introduction` : undefined;

  return (
    <section
      id={id}
      className={styles.section}
      aria-labelledby={titleId}
      aria-describedby={introductionId}
    >
      <header className={styles.sectionHeader}>
        <h2 id={titleId} className={styles.sectionTitle}>{title}</h2>
        {introduction ? (
          <p id={introductionId} className={styles.sectionIntroduction}>{introduction}</p>
        ) : null}
      </header>
      {children}
    </section>
  );
};
