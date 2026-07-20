import { Link, useParams } from 'react-router-dom';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { KnowledgeRenderer } from '../../features/knowledge/KnowledgeRenderer';
import { knowledgeRepository } from '../../features/knowledge/knowledgeRepository';
import { manualPath } from '../../features/knowledge/knowledgeRoutes';
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

  if (!document) {
    return <NotFoundPage />;
  }

  return (
    <article className={styles.document}>
      <PageMetadata
        title={`${document.title} | Manual | Galotxas`}
        description={`Consulta ${document.title} en el Manual público de Galotxas.`}
      />
      <Link className={styles.backLink} to={manualPath()}>← Volver al Manual</Link>
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
      <KnowledgeRenderer blocks={document.blocks} />
      <footer className={styles.documentFooter}>
        <Link className={styles.backLink} to={manualPath()}>← Volver al Manual</Link>
      </footer>
    </article>
  );
};
