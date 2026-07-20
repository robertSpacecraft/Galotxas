import { Link } from 'react-router-dom';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { knowledgeRepository } from '../../features/knowledge/knowledgeRepository';
import styles from './Learn.module.css';

export const ManualPage = () => {
  const collections = knowledgeRepository.getCollectionsWithDocuments();

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
      />
      <div className={styles.manualSections}>
        {collections.map((collection) => (
          <LandingSection
            key={collection.id}
            id={`manual-${collection.id.replace('/', '-')}`}
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
