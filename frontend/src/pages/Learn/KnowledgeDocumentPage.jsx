import { useParams } from 'react-router-dom';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicContentSurface } from '../../components/PublicContentSurface/PublicContentSurface';
import { KnowledgeContextNavigation } from '../../features/knowledge/KnowledgeContextNavigation';
import { KnowledgeDocumentNavigation } from '../../features/knowledge/KnowledgeDocumentNavigation';
import { KnowledgeRenderer } from '../../features/knowledge/KnowledgeRenderer';
import { KnowledgeTableOfContents } from '../../features/knowledge/KnowledgeTableOfContents';
import { knowledgeRepository } from '../../features/knowledge/knowledgeRepository';
import { useKnowledgeFragment } from '../../features/knowledge/useKnowledgeFragment';
import { NotFoundPage } from '../NotFound/NotFoundPage';
import styles from './Learn.module.css';

const formatRevision = (revision) => {
  const [year, month, day] = revision.split('-');
  return `${day}/${month}/${year}`;
};

export const KnowledgeDocumentPage = ({ type }) => {
  const { group, slug } = useParams();
  const document = type === 'regulation'
    ? knowledgeRepository.getRegulationBySlug(slug)
    : knowledgeRepository.getConceptByGroupAndSlug(group, slug);
  const context = knowledgeRepository.getDocumentContext(document);

  useKnowledgeFragment(document?.id);

  if (!document || !context) {
    return <NotFoundPage />;
  }

  return (
    <PublicContentSurface>
      <article className={styles.document}>
        <PageMetadata
          title={`${document.title} | Manual`}
          description={`Consulta ${document.title} en el Manual público de Galotxas.`}
        />
        <KnowledgeContextNavigation collection={context.collection} />
        <header className={styles.documentHeader}>
          <h1>{document.title}</h1>
          <dl className={styles.metadata}>
            <div>
              <dt>Identificador</dt>
              <dd>{document.id}</dd>
            </div>
            <div>
              <dt>Versión</dt>
              <dd>{document.version}</dd>
            </div>
            <div>
              <dt>Última revisión</dt>
              <dd><time dateTime={document.lastRevision}>{formatRevision(document.lastRevision)}</time></dd>
            </div>
          </dl>
        </header>
        <KnowledgeTableOfContents document={document} />
        <KnowledgeRenderer blocks={document.blocks} />
        <KnowledgeDocumentNavigation context={context} />
      </article>
    </PublicContentSurface>
  );
};

export default KnowledgeDocumentPage;
