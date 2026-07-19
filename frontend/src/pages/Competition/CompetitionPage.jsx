import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingLinkCard } from '../../components/PublicLanding/LandingLinkCard';
import { LandingLinkGrid } from '../../components/PublicLanding/LandingLinkGrid';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { useCompetitionOverview } from '../../hooks/useCompetitionOverview';
import { CompetitionOverview } from './CompetitionOverview';
import styles from './CompetitionPage.module.css';

export const CompetitionPage = () => {
  const {
    data: seasons,
    status,
    error,
    reload,
  } = useCompetitionOverview();

  return (
    <PublicLanding>
      <PageMetadata
        title="Competición | Galotxas"
        description="Consulta temporadas y campeonatos públicos, calendarios, resultados y clasificaciones de Galotxas."
      />
      <LandingHeader
        id="competition-header"
        title="Competición"
        introduction="Consulta temporadas, campeonatos, calendarios, resultados y clasificaciones de Galotxas."
      />
      <div className={styles.sections}>
        <LandingSection
          id="competition-overview"
          title="Temporadas y campeonatos"
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
          {status === 'content' ? <CompetitionOverview seasons={seasons} /> : null}
        </LandingSection>
        <LandingSection id="competition-destinations" title="Torneos y rankings">
          <LandingLinkGrid label="Opciones de competición">
            <LandingLinkCard
              to="/torneos"
              title="Torneos"
              description="Explora los campeonatos y accede a sus categorías, calendarios y resultados."
            />
            <LandingLinkCard
              to="/rankings"
              title="Rankings"
              description="Revisa la clasificación histórica y el rendimiento por temporadas."
            />
          </LandingLinkGrid>
        </LandingSection>
      </div>
    </PublicLanding>
  );
};
