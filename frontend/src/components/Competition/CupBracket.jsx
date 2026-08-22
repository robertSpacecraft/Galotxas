import { Link } from 'react-router-dom';
import { getMatchDetailPath } from '../../navigation/competitionRoutes';
import { getPublicCompetitionDisplayName } from '../../utils/publicCompetitionIdentity';
import { getMatchStatusLabel } from '../../pages/Competition/competitionPresentation';
import styles from './CupBracket.module.css';

const STAGES = [
  { id: 'semifinal', title: 'Semifinales', matchLabel: 'Semifinal' },
  { id: 'final', title: 'Final', matchLabel: 'Final' },
  { id: 'third_place', title: 'Tercer y cuarto puesto', matchLabel: 'Tercer y cuarto puesto' },
];

const formatScheduledDate = (value) => {
  if (!value) return null;

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;

  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
};

function CupMatch({ match, label }) {
  const homeName = getPublicCompetitionDisplayName(match?.home_entry);
  const awayName = getPublicCompetitionDisplayName(match?.away_entry);
  const winnerName = match?.status === 'validated' && match?.winner_entry
    ? getPublicCompetitionDisplayName(match.winner_entry)
    : null;
  const scheduledLabel = formatScheduledDate(match?.scheduled_date);
  const detailPath = getMatchDetailPath(match?.id);
  const headingId = `cup-match-${match?.id || label.replaceAll(' ', '-').toLowerCase()}`;
  const showScores = match?.status === 'validated';

  return (
    <article className={styles.matchCard} aria-labelledby={headingId}>
      <div className={styles.matchHeader}>
        <h4 id={headingId}>{label}</h4>
        <span className={styles.status}>{getMatchStatusLabel(match?.status)}</span>
      </div>

      {scheduledLabel ? (
        <time className={styles.schedule} dateTime={match.scheduled_date}>{scheduledLabel}</time>
      ) : (
        <span className={styles.schedule}>Fecha y hora por definir</span>
      )}

      <div className={styles.participants}>
        <p>
          <span>{homeName}</span>
          <strong aria-label={`Tanteo de ${homeName}`}>
            {showScores && Number.isInteger(match?.home_score) ? match.home_score : '—'}
          </strong>
        </p>
        <p>
          <span>{awayName}</span>
          <strong aria-label={`Tanteo de ${awayName}`}>
            {showScores && Number.isInteger(match?.away_score) ? match.away_score : '—'}
          </strong>
        </p>
      </div>

      <p className={styles.venue}>Pista: {match?.venue?.name || 'Por definir'}</p>
      {winnerName ? <p className={styles.winner}>Ganador: <strong>{winnerName}</strong></p> : null}
      {detailPath ? (
        <Link className={styles.detailLink} to={detailPath} aria-label={`Ver detalle de ${label}`}>
          Ver partido
        </Link>
      ) : null}
    </article>
  );
}

const getPendingMessage = (stage) => {
  if (stage === 'semifinal') return 'Semifinales pendientes de configuración';
  if (stage === 'final') return 'Final pendiente de generación';

  return 'Tercer y cuarto puesto pendiente de generación';
};

export function CupBracket({ rounds = [] }) {
  const roundsByStage = new Map(
    rounds
      .filter((round) => round?.type === 'cup' && round?.phase === 'cup')
      .filter((round) => STAGES.some(({ id }) => id === round.stage))
      .map((round) => [round.stage, round]),
  );
  const semifinalRound = roundsByStage.get('semifinal');
  const finalRound = roundsByStage.get('final');
  const finalMatch = finalRound?.matches?.[0];
  const champion = finalMatch?.status === 'validated' && finalMatch?.winner_entry
    ? getPublicCompetitionDisplayName(finalMatch.winner_entry)
    : null;

  if (roundsByStage.size === 0) return null;

  return (
    <section className={styles.cupSection} aria-labelledby="cup-heading">
      <div className={styles.titleRow}>
        <div>
          <p className={styles.eyebrow}>Fase eliminatoria</p>
          <h2 id="cup-heading">Copa</h2>
        </div>
      </div>

      <div className={styles.bracket}>
        {STAGES.map((stage) => {
          const round = roundsByStage.get(stage.id);
          const showPending = semifinalRound && !round && stage.id !== 'semifinal';

          if (!round && !showPending) return null;

          return (
            <section key={stage.id} className={styles.stage} aria-labelledby={`cup-stage-${stage.id}`}>
              <h3 id={`cup-stage-${stage.id}`}>{stage.title}</h3>
              {round && Array.isArray(round.matches) && round.matches.length > 0 ? (
                <div className={styles.stageMatches}>
                  {round.matches.map((match, index) => (
                    <CupMatch
                      key={match.id || `${stage.id}-${index}`}
                      match={match}
                      label={round.matches.length > 1 ? `${stage.matchLabel} ${index + 1}` : stage.matchLabel}
                    />
                  ))}
                </div>
              ) : (
                <p className={styles.pending}>{getPendingMessage(stage.id)}</p>
              )}
            </section>
          );
        })}
      </div>
      {champion ? (
        <p className={styles.champion}>
          <span>Campeón de Copa</span>
          <strong>{champion}</strong>
        </p>
      ) : null}
    </section>
  );
}
