import { LandingActions } from '../../components/PublicLanding/LandingActions';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { knowledgeRepository } from '../../features/knowledge/knowledgeRepository';
import { manualPath } from '../../features/knowledge/knowledgeRoutes';
import styles from './Learn.module.css';

const manualActions = [
  { to: manualPath(), label: 'Consultar el Manual', variant: 'primary' },
];

export const LearnPage = () => {
  const collectionCount = knowledgeRepository.getCollections().length;
  const documentCount = knowledgeRepository.getDocuments().length;

  return (
    <PublicLanding>
      <PageMetadata
        title="Aprende a jugar | Galotxas"
        description="Aprende las reglas y los conceptos esenciales de Galotxas mediante el Manual público."
      />
      <LandingHeader
        id="learn"
        title="Aprende a jugar"
        introduction="Consulta las reglas y los conceptos esenciales para comprender el juego."
      />
      <LandingSection
        id="learn-manual"
        title="Manual público"
        introduction={`Recorre ${documentCount} documentos organizados en ${collectionCount} colecciones de Reglamento y Conceptos.`}
      >
        <div className={styles.learnAction}>
          <LandingActions label="Acceso al Manual" actions={manualActions} />
        </div>
      </LandingSection>
    </PublicLanding>
  );
};

export default LearnPage;
