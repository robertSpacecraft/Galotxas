import { LandingActions } from '../../components/PublicLanding/LandingActions';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { manualPath } from '../../features/knowledge/knowledgeRoutes';

const manualActions = [
  { to: manualPath(), label: 'Consultar el Manual', variant: 'primary' },
];

export const LearnPage = () => (
  <PublicLanding>
    <PageMetadata
      title="Aprende a jugar | Galotxas"
      description="Aprende las reglas y los conceptos esenciales de Galotxas mediante el Manual público."
    />
    <LandingHeader
      id="learn"
      title="Aprende a jugar"
      introduction="Consulta las reglas y los conceptos esenciales para comprender el juego."
      actions={<LandingActions label="Acceso al Manual" actions={manualActions} />}
    />
  </PublicLanding>
);
