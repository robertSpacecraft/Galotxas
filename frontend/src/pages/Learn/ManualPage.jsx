import { Link } from 'react-router-dom';
import { LandingActions } from '../../components/PublicLanding/LandingActions';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { knowledgeRepository } from '../../features/knowledge/knowledgeRepository';
import {
  knowledgeCollectionAnchor,
  learnPath,
  manualCollectionPath,
} from '../../features/knowledge/knowledgeRoutes';
import { useKnowledgeFragment } from '../../features/knowledge/useKnowledgeFragment';
import styles from './Learn.module.css';

const learnActions = [
  { to: learnPath(), label: 'Volver a Aprende a jugar', variant: 'secondary' },
];

export const ManualPage = () => {
  const collections = knowledgeRepository.getCollectionsWithDocuments();
  useKnowledgeFragment('manual', 'section[id^="manual-"]');

  return (
    <PublicLanding>
      <PageMetadata
        title="Manual | Aprende a jugar | Galotxas"
        description="Consulta el reglamento y los conceptos del Manual público de Galotxas."
      />
      <LandingHeader
        id="manual"
        title="Manual"
        introduction="Reglamento y conceptos de referencia para conocer Galotxas."
        actions={<LandingActions label="Navegación de Aprende a jugar" actions={learnActions} />}
      />
      <nav className={styles.collectionNavigation} aria-label="Colecciones del Manual">
        <span>Ir a una colección:</span>
        <ul>
          {collections.map((collection) => (
            <li key={collection.id}>
              <Link to={manualCollectionPath(collection.id)}>{collection.title}</Link>
            </li>
          ))}
        </ul>
      </nav>
      <div className={styles.manualSections}>
        {collections.map((collection) => (
          <LandingSection
            key={collection.id}
            id={knowledgeCollectionAnchor(collection.id)}
            title={collection.title}
          >
            <ol className={styles.documentList}>
              {collection.documents.map((document) => (
                <li key={document.id}>
                  <Link to={document.route}>{document.title}</Link>
                </li>
              ))}
            </ol>
          </LandingSection>
        ))}
      </div>
    </PublicLanding>
  );
};

export default ManualPage;
