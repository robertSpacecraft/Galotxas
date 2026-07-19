import { CompetitionChampionshipCard } from './CompetitionChampionshipCard';
import {
  getCompetitionDateLabel,
  getSeasonStatusLabel,
} from './competitionPresentation';
import styles from './CompetitionPage.module.css';

const SeasonDates = ({ startDate, endDate }) => {
  const startLabel = getCompetitionDateLabel(startDate);
  const endLabel = getCompetitionDateLabel(endDate);

  if (!startLabel && !endLabel) {
    return null;
  }

  return (
    <div className={styles.detail}>
      <dt>Fechas</dt>
      <dd>
        {startLabel ? <time dateTime={startDate}>{startLabel}</time> : null}
        {startLabel && endLabel ? ' – ' : null}
        {endLabel ? <time dateTime={endDate}>{endLabel}</time> : null}
      </dd>
    </div>
  );
};

export const CompetitionSeason = ({ season }) => {
  const titleId = `competition-season-${season.id}-title`;
  const championships = Array.isArray(season.championships) ? season.championships : [];
  const championshipsLabel = championships.length === 1
    ? '1 campeonato'
    : `${championships.length} campeonatos`;

  return (
    <section className={styles.season} aria-labelledby={titleId}>
      <header className={styles.seasonHeader}>
        <div>
          <p className={styles.seasonEyebrow}>Temporada</p>
          <h3 id={titleId} className={styles.seasonTitle}>{season.name}</h3>
        </div>
        <p className={styles.seasonCount}>{championshipsLabel}</p>
      </header>
      <dl className={styles.seasonDetails}>
        <div className={styles.detail}>
          <dt>Estado</dt>
          <dd>{getSeasonStatusLabel(season.status)}</dd>
        </div>
        <SeasonDates startDate={season.start_date} endDate={season.end_date} />
      </dl>
      {championships.length > 0 ? (
        <div className={styles.championshipGrid}>
          {championships.map((championship) => (
            <CompetitionChampionshipCard
              key={championship.id}
              championship={championship}
            />
          ))}
        </div>
      ) : (
        <p className={styles.localEmpty}>Esta temporada todavía no tiene campeonatos disponibles.</p>
      )}
    </section>
  );
};
