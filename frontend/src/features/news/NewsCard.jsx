import { Link } from 'react-router-dom';
import { formatDate } from '../../utils/formatDate';
import { NewsImage } from './NewsImage';
import { newsArticlePath } from './newsRoutes';
import styles from './NewsPages.module.css';

export const NewsCard = ({ article, featured = false }) => (
  <article className={`${styles.card} ${featured ? styles.featuredCard : ''}`}>
    <div className={styles.cardImageFrame}>
      <NewsImage
        image={article.image}
        eager={featured}
        className={styles.cardImage}
      />
    </div>
    <div className={styles.cardContent}>
      {featured ? <p className={styles.eyebrow}>Última noticia</p> : null}
      <h2 className={styles.cardTitle}>{article.title}</h2>
      <time className={styles.date} dateTime={article.published_at}>
        {formatDate(article.published_at)}
      </time>
      <p className={styles.excerpt}>{article.excerpt}</p>
      <Link className={styles.articleLink} to={newsArticlePath(article.slug)}>
        Leer noticia: {article.title}
      </Link>
    </div>
  </article>
);
