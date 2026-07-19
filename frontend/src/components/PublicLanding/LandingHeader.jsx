import styles from './PublicLanding.module.css';

export const LandingHeader = ({ id, title, introduction, actions = null }) => {
  const titleId = `${id}-title`;
  const introductionId = introduction ? `${id}-introduction` : undefined;

  return (
    <header
      className={styles.header}
      aria-labelledby={titleId}
      aria-describedby={introductionId}
    >
      <h1 id={titleId} className={styles.title}>{title}</h1>
      {introduction ? (
        <p id={introductionId} className={styles.introduction}>{introduction}</p>
      ) : null}
      {actions ? <div className={styles.headerActions}>{actions}</div> : null}
    </header>
  );
};
