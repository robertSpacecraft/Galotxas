import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingLinkCard } from '../../components/PublicLanding/LandingLinkCard';
import { LandingLinkGrid } from '../../components/PublicLanding/LandingLinkGrid';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';

export const CompetitionPage = () => (
  <PublicLanding>
    <PageMetadata
      title="Competición | Galotxas"
      description="Consulta campeonatos, categorías, calendarios, resultados y clasificaciones de Galotxas."
    />
    <LandingHeader
      id="competition-header"
      title="Competición"
      introduction="Consulta campeonatos, categorías, calendarios, resultados y clasificaciones de Galotxas."
    />
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
  </PublicLanding>
);
