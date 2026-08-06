import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { cmsService } from '../../api/cms';
import { CmsBlockRenderer } from '../../components/CmsBlocks/CmsBlockRenderer';
import { PublicContentSurface } from '../../components/PublicContentSurface/PublicContentSurface';
import styles from './CmsPage.module.css';

export const CmsPage = () => {
  const { slug } = useParams();
  const [page, setPage] = useState(null);
  const [status, setStatus] = useState('loading');
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    let ignore = false;

    const loadPage = async () => {
      setStatus('loading');
      setErrorMessage('');

      try {
        const cmsPage = await cmsService.getPageBySlug(slug);

        if (!ignore) {
          setPage(cmsPage);
          setStatus('content');
        }
      } catch (error) {
        if (!ignore) {
          setPage(null);
          setStatus(error.status === 404 ? 'notFound' : 'error');
          setErrorMessage('No se ha podido cargar la página.');
        }
      }
    };

    loadPage();

    return () => {
      ignore = true;
    };
  }, [slug]);

  useEffect(() => {
    if (!page) {
      return;
    }

    document.title = `${page.seo_title || page.title} | Galotxas`;
  }, [page]);

  if (status === 'loading') {
    return (
      <PublicContentSurface>
        <div className={styles.container}>
          <div className={styles.stateMessage}>Cargando contenido...</div>
        </div>
      </PublicContentSurface>
    );
  }

  if (status === 'notFound') {
    return (
      <PublicContentSurface>
        <div className={styles.container}>
          <div className={styles.stateMessage}>
            <h1>Página no encontrada</h1>
            <p>El contenido solicitado no está disponible.</p>
          </div>
        </div>
      </PublicContentSurface>
    );
  }

  if (status === 'error') {
    return (
      <PublicContentSurface>
        <div className={styles.container}>
          <div className={styles.stateMessage}>
            <h1>Error de carga</h1>
            <p>{errorMessage}</p>
          </div>
        </div>
      </PublicContentSurface>
    );
  }

  const blocks = Array.isArray(page?.blocks) ? page.blocks : [];

  return (
    <PublicContentSurface>
      <article className={styles.container}>
        <header className={styles.header}>
          <h1 className={styles.title}>{page.title}</h1>
          {page.seo_description ? (
            <p className={styles.description}>{page.seo_description}</p>
          ) : null}
        </header>

        {blocks.length > 0 ? (
          <div className={styles.blocks}>
            {blocks.map((block, index) => (
              <CmsBlockRenderer
                key={`${block.type}-${block.order ?? index}-${index}`}
                block={block}
              />
            ))}
          </div>
        ) : (
          <div className={styles.stateMessage}>Esta página no tiene contenido publicado.</div>
        )}
      </article>
    </PublicContentSurface>
  );
};
