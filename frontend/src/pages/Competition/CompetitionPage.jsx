import { Link } from 'react-router-dom';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { useAllTimeRanking } from '../../hooks/useAllTimeRanking';
import { useCompetitionOverview } from '../../hooks/useCompetitionOverview';
import {
  RANKINGS_PATH,
} from '../../navigation/competitionRoutes';
import { CompetitionIndexLink } from './CompetitionIndexLink';
import { CompetitionOverview } from './CompetitionOverview';
import { CompetitionRankingPreview } from './CompetitionRankingPreview';
import styles from './CompetitionPage.module.css';

export const CompetitionPage = () => {
  const {
    data: seasons,
    status,
    error,
    reload,
  } = useCompetitionOverview();
  const {
    data: allTimeRanking,
    status: rankingStatus,
    error: rankingError,
    reload: reloadRanking,
  } = useAllTimeRanking();

  return (
    <PublicLanding>
      <PageMetadata
        title="Competición"
        description="Consulta temporadas y campeonatos públicos, calendarios, resultados y clasificaciones de Galotxas."
      />
      <LandingHeader
        id="competition-header"
        title="Competición"
        introduction="Consulta campeonatos, clasificaciones, calendarios y resultados de Galotxas."
      />
      <div className={styles.sections}>
        {status === 'content' ? <CompetitionOverview seasons={seasons} /> : (
          <LandingSection
            id="competition-overview"
            title="Campeonatos"
            introduction="Descubre la competición disponible y accede al detalle de cada campeonato."
          >
            {status === 'loading' ? (
              <p className={styles.remoteState} role="status" aria-live="polite">
                Cargando temporadas y campeonatos…
              </p>
            ) : null}
            {status === 'error' ? (
              <div className={styles.errorState} role="alert">
                <p>{error}</p>
                <button type="button" className={styles.retryButton} onClick={reload}>
                  Reintentar
                </button>
              </div>
            ) : null}
            {status === 'empty' ? (
              <p className={styles.remoteState}>
                No hay temporadas disponibles en este momento.
              </p>
            ) : null}
            <CompetitionIndexLink />
          </LandingSection>
        )}
        <LandingSection
          id="competition-ranking-preview"
          title="Ranking histórico"
          introduction="Consulta las primeras posiciones del ranking histórico global devuelto por Galotxas."
        >
          {rankingStatus === 'loading' ? (
            <p className={styles.remoteState} role="status" aria-live="polite">
              Cargando ranking histórico…
            </p>
          ) : null}
          {rankingStatus === 'error' ? (
            <div className={styles.errorState} role="alert">
              <p>{rankingError}</p>
              <button type="button" className={styles.retryButton} onClick={reloadRanking}>
                Reintentar ranking
              </button>
            </div>
          ) : null}
          {rankingStatus === 'empty' ? (
            <p className={styles.remoteState}>
              Todavía no hay datos disponibles en el ranking histórico.
            </p>
          ) : null}
          {rankingStatus === 'content' ? (
            <CompetitionRankingPreview ranking={allTimeRanking} />
          ) : null}
          <Link to={RANKINGS_PATH} className={styles.rankingFullLink}>
            Ver ranking completo
          </Link>
        </LandingSection>
      </div>
    </PublicLanding>
  );
};
