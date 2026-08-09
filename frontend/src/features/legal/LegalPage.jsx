import { PublicContentSurface } from '../../components/PublicContentSurface/PublicContentSurface';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { NotFoundPage } from '../../pages/NotFound/NotFoundPage';
import { LegalNavigation } from './LegalNavigation';
import { LegalRenderer } from './LegalRenderer';
import { legalRepository } from './legalRepository';
import styles from './LegalPage.module.css';

const formatDate = (isoDate) => new Intl.DateTimeFormat('es-ES', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  timeZone: 'UTC',
}).format(new Date(`${isoDate}T00:00:00Z`));

export const LegalPage = ({ pageId }) => {
  const document = legalRepository.getDocumentById(pageId);

  if (!document) return <NotFoundPage />;

  return (
    <PublicContentSurface>
      <div className={styles.page}>
        <PageMetadata
          title={document.title}
          description={document.summary}
        />
        <LegalNavigation />
        <article className={styles.document}>
          <header className={styles.header}>
            <p className={styles.eyebrow}>Información legal</p>
            <h1>{document.title}</h1>
            <dl className={styles.metadata}>
              <div>
                <dt>Versión</dt>
                <dd>{document.version}</dd>
              </div>
              <div>
                <dt>Publicada</dt>
                <dd>
                  <time dateTime={document.publishedAt}>{formatDate(document.publishedAt)}</time>
                </dd>
              </div>
            </dl>
          </header>
          <LegalRenderer blocks={document.blocks} />
        </article>
      </div>
    </PublicContentSurface>
  );
};

export default LegalPage;
