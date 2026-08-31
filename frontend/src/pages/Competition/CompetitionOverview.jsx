import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { CompetitionHistoricalSeasonList } from './CompetitionHistoricalSeasonList';
import { CompetitionIndexLink } from './CompetitionIndexLink';
import { CompetitionSeason } from './CompetitionSeason';
import { groupCompetitionSeasons } from './competitionSeasonGroups';
import styles from './CompetitionPage.module.css';

export const CompetitionOverview = ({ seasons }) => {
  const {
    activeSeasons,
    plannedSeasons,
    referenceSeason,
    historicalSeasons,
    hasVisibleSeasons,
  } = groupCompetitionSeasons(seasons);
  const hasActiveSeasons = activeSeasons.length > 0;

  if (!hasVisibleSeasons) {
    return (
      <LandingSection
        id="competition-unavailable"
        title="Campeonatos"
        introduction="Consulta el explorador global para comprobar los campeonatos públicos disponibles."
      >
        <p className={styles.remoteState}>
          No hay temporadas en curso, próximas o finalizadas disponibles en este momento.
        </p>
        <CompetitionIndexLink />
      </LandingSection>
    );
  }

  return (
    <>
      {hasActiveSeasons ? (
        <LandingSection
          id="competition-current-seasons"
          title={activeSeasons.length === 1 ? 'Temporada en curso' : 'Temporadas en curso'}
          introduction="Accede directamente a los campeonatos públicos que se están disputando."
        >
          <div className={styles.currentSeasonList}>
            {activeSeasons.map((season) => (
              <CompetitionSeason key={season.id} season={season} appearance="current" />
            ))}
          </div>
          <CompetitionIndexLink />
        </LandingSection>
      ) : null}

      {plannedSeasons.length > 0 ? (
        <LandingSection
          id="competition-upcoming-seasons"
          title={!hasActiveSeasons && plannedSeasons.length === 1 ? 'Próxima temporada' : 'Próximamente'}
          introduction="Consulta los campeonatos públicos que ya están planificados."
        >
          <div className={styles.upcomingSeasonList}>
            {plannedSeasons.map((season) => (
              <CompetitionSeason key={season.id} season={season} appearance="upcoming" />
            ))}
          </div>
          {!hasActiveSeasons ? <CompetitionIndexLink /> : null}
        </LandingSection>
      ) : null}

      {referenceSeason ? (
        <LandingSection
          id="competition-latest-season"
          title="Última temporada disponible"
          introduction="No hay una temporada en curso o próxima; esta es la referencia finalizada más reciente."
        >
          <CompetitionHistoricalSeasonList seasons={[referenceSeason]} />
          {historicalSeasons.length === 0 ? (
            <CompetitionIndexLink>Ver todas las temporadas</CompetitionIndexLink>
          ) : null}
        </LandingSection>
      ) : null}

      {historicalSeasons.length > 0 ? (
        <LandingSection
          id="competition-previous-seasons"
          title="Temporadas anteriores"
          introduction="Accede al archivo de campeonatos de las temporadas finalizadas más recientes."
        >
          <CompetitionHistoricalSeasonList seasons={historicalSeasons} />
          <CompetitionIndexLink>Ver todas las temporadas</CompetitionIndexLink>
        </LandingSection>
      ) : null}
    </>
  );
};
