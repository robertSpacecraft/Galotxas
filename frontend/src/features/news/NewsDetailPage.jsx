import { Link, useParams } from 'react-router-dom';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { NotFoundPage } from '../../pages/NotFound/NotFoundPage';
import { seoRouteClassifications } from '../../seo/seoManifest';
import { formatDate } from '../../utils/formatDate';
import { NewsImage } from './NewsImage';
import { newsArticlePath, newsPath } from './newsRoutes';
import { useNewsArticle } from './useNewsArticle';
import styles from './NewsPages.module.css';

const NewsDetailPage = () => {
  const { slug } = useParams();
  const { article, status, error, reload } = useNewsArticle(slug);

  if (status === 'not-found') {
    return <NotFoundPage />;
  }

  if (status !== 'content') {
    return (
      <div className={styles.page}>
        <div className={styles.detailState}>
          <Link className={styles.contextLink} to={newsPath()}>Noticias</Link>
          {status === 'loading' ? (
            <p role="status" aria-live="polite">Cargando noticia…</p>
          ) : (
            <div className={styles.errorState} role="alert">
              <p>{error}</p>
              <button type="button" className={styles.button} onClick={reload}>
                Reintentar
              </button>
            </div>
          )}
        </div>
      </div>
    );
  }

  const paragraphs = article.body
    .split(/\r?\n\s*\r?\n/u)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean);

  return (
    <div className={styles.page}>
      <article className={styles.detailArticle}>
        <PageMetadata
          title={article.seo_title || article.title}
          description={article.seo_description || article.excerpt}
          classification={seoRouteClassifications.indexableCanonical}
          canonicalPath={newsArticlePath(article.slug)}
          article={{
            headline: article.title,
            publishedAt: article.published_at,
            image: article.image.url,
          }}
        />
        <Link className={styles.contextLink} to={newsPath()}>Noticias</Link>
        <header className={styles.detailHeader}>
          <h1>{article.title}</h1>
          <time className={styles.date} dateTime={article.published_at}>
            {formatDate(article.published_at)}
          </time>
          <p className={styles.detailExcerpt}>{article.excerpt}</p>
        </header>

        <figure className={styles.detailFigure}>
          <NewsImage image={article.image} eager className={styles.detailImage} />
          {article.image.credit ? <figcaption>{article.image.credit}</figcaption> : null}
        </figure>

        <div className={styles.body}>{paragraphs.map((paragraph, index) => (
          <p key={`${article.slug}-${index}`}>{paragraph}</p>
        ))}</div>

        <Link className={styles.backLink} to={newsPath()}>Volver a Noticias</Link>
      </article>
    </div>
  );
};

export default NewsDetailPage;
