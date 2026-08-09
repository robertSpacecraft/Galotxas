import { CmsBlockRenderer } from '../../components/CmsBlocks/CmsBlockRenderer';
import { PublicContentSurface } from '../../components/PublicContentSurface/PublicContentSurface';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { NotFoundPage } from '../../pages/NotFound/NotFoundPage';
import { seoRouteClassifications } from '../../seo/seoManifest';
import { ContactPanel } from './ContactPanel';
import { getClubPage } from './clubRoutes';
import { useClubPage } from './useClubPage';
import styles from './ClubPage.module.css';

const ClubContent = ({ config, page }) => {
  const blocks = page.blocks;

  return (
    <PublicContentSurface>
      <article className={styles.page}>
        <PageMetadata
          title={page.seo_title || page.title}
          description={page.seo_description || config.description}
        />
        <header className={styles.header}>
          <h1>{page.title}</h1>
        </header>

        {blocks.length > 0 ? (
          <div className={styles.blocks}>
            {blocks.map((block, index) => (
              <CmsBlockRenderer
                key={`${block?.type ?? 'unknown'}-${block?.order ?? index}-${index}`}
                block={block}
              />
            ))}
          </div>
        ) : (
          <p className={styles.emptyState}>Esta página no tiene contenido publicado.</p>
        )}

        {config.id === 'contact' ? <ContactPanel /> : null}
      </article>
    </PublicContentSurface>
  );
};

export const ClubPage = ({ pageId }) => {
  const config = getClubPage(pageId);

  if (!config) {
    return <NotFoundPage />;
  }

  return <ConfiguredClubPage config={config} />;
};

const ConfiguredClubPage = ({ config }) => {
  const {
    page,
    status,
    error,
    reload,
  } = useClubPage(config);

  if (status === 'notFound') {
    return <NotFoundPage />;
  }

  if (status === 'content') {
    return <ClubContent config={config} page={page} />;
  }

  return (
    <PublicContentSurface>
      <section className={styles.page}>
        <PageMetadata
          title={status === 'error' ? 'Error de carga' : config.title}
          description={status === 'error'
            ? 'No se ha podido cargar la información institucional solicitada.'
            : config.description}
          classification={status === 'error'
            ? seoRouteClassifications.noindexPublic
            : undefined}
          canonicalPath={status === 'error' ? null : undefined}
        />
        {status === 'loading' ? (
          <p className={styles.remoteState} role="status" aria-live="polite">
            Cargando contenido…
          </p>
        ) : null}
        {status === 'error' ? (
          <div className={styles.errorState} role="alert">
            <h1>{config.title}</h1>
            <p>{error}</p>
            <button type="button" className={styles.secondaryButton} onClick={reload}>
              Reintentar
            </button>
          </div>
        ) : null}
      </section>
    </PublicContentSurface>
  );
};

export default ClubPage;
