import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { NewsCard } from './NewsCard';
import { useNewsList } from './useNewsList';
import styles from './NewsPages.module.css';

const RemoteError = ({ message, onRetry, retryLabel = 'Reintentar' }) => (
  <div className={styles.errorState} role="alert">
    <p>{message}</p>
    <button type="button" className={styles.button} onClick={onRetry}>
      {retryLabel}
    </button>
  </div>
);

const NewsIndexPage = () => {
  const {
    articles,
    meta,
    status,
    loadMoreStatus,
    error,
    loadMoreError,
    reload,
    loadMore,
  } = useNewsList();
  const [featured, ...remaining] = articles;

  return (
    <div className={styles.page}>
      <LandingHeader
        id="news-header"
        title="Noticias"
        introduction="Actualidad y actividad pública del Club Galotxes Monòver."
      />

      {status === 'loading' ? (
        <p className={styles.remoteState} role="status" aria-live="polite">
          Cargando noticias…
        </p>
      ) : null}
      {status === 'error' || status === 'invalid' ? (
        <RemoteError message={error} onRetry={reload} />
      ) : null}
      {status === 'empty' ? (
        <p className={styles.remoteState}>No hay noticias publicadas en este momento.</p>
      ) : null}
      {status === 'content' ? (
        <section className={styles.newsSection} aria-label="Noticias publicadas">
          <NewsCard article={featured} featured />
          {remaining.length > 0 ? (
            <div className={styles.cardGrid}>{remaining.map((article) => (
              <NewsCard key={article.slug} article={article} />
            ))}</div>
          ) : null}

          {loadMoreError ? (
            <RemoteError
              message={loadMoreError}
              onRetry={loadMore}
              retryLabel="Reintentar cargar más"
            />
          ) : null}
          {meta?.has_more ? (
            <button
              type="button"
              className={styles.loadMoreButton}
              disabled={loadMoreStatus === 'loading'}
              onClick={loadMore}
            >
              {loadMoreStatus === 'loading' ? 'Cargando más…' : 'Cargar más'}
            </button>
          ) : null}
        </section>
      ) : null}
    </div>
  );
};

export default NewsIndexPage;
