import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { cmsService } from '../../api/cms';
import styles from './CmsPageIndex.module.css';

const formatPublishedDate = (date) => {
  if (!date) {
    return null;
  }

  const parsedDate = new Date(date);

  if (Number.isNaN(parsedDate.getTime())) {
    return null;
  }

  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(parsedDate);
};

export const CmsPageIndex = () => {
  const [pages, setPages] = useState([]);
  const [status, setStatus] = useState('loading');
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    let ignore = false;

    const loadPages = async () => {
      setStatus('loading');
      setErrorMessage('');

      try {
        const publishedPages = await cmsService.getPublishedPages();

        if (!ignore) {
          const normalizedPages = Array.isArray(publishedPages) ? publishedPages : [];

          setPages(normalizedPages);
          setStatus(normalizedPages.length > 0 ? 'content' : 'empty');
        }
      } catch (error) {
        if (!ignore) {
          setPages([]);
          setStatus('error');
          setErrorMessage(error.message || 'No se ha podido cargar el índice de contenidos.');
        }
      }
    };

    document.title = 'Contenidos | Galotxas';
    loadPages();

    return () => {
      ignore = true;
    };
  }, []);

  if (status === 'loading') {
    return (
      <main className={styles.container}>
        <div className={styles.stateMessage}>Cargando contenidos...</div>
      </main>
    );
  }

  if (status === 'error') {
    return (
      <main className={styles.container}>
        <div className={styles.stateMessage}>
          <h1>Error de carga</h1>
          <p>{errorMessage}</p>
        </div>
      </main>
    );
  }

  if (status === 'empty') {
    return (
      <main className={styles.container}>
        <header className={styles.header}>
          <h1 className={styles.title}>Contenidos</h1>
          <p className={styles.subtitle}>Información pública de Galotxas.</p>
        </header>
        <div className={styles.stateMessage}>No hay contenidos publicados todavía.</div>
      </main>
    );
  }

  return (
    <main className={styles.container}>
      <header className={styles.header}>
        <h1 className={styles.title}>Contenidos</h1>
        <p className={styles.subtitle}>Información pública de Galotxas.</p>
      </header>

      <div className={styles.grid}>
        {pages.map((page) => {
          const publishedDate = formatPublishedDate(page.published_at);

          return (
            <article className={styles.card} key={page.slug}>
              <div className={styles.cardBody}>
                <h2 className={styles.cardTitle}>{page.title}</h2>
                {page.seo_description ? (
                  <p className={styles.description}>{page.seo_description}</p>
                ) : null}
                {publishedDate ? (
                  <time className={styles.date} dateTime={page.published_at}>
                    {publishedDate}
                  </time>
                ) : null}
              </div>
              <Link to={page.url || `/contenidos/${page.slug}`} className={styles.link}>
                Ver contenido
              </Link>
            </article>
          );
        })}
      </div>
    </main>
  );
};
