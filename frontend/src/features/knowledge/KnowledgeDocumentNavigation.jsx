import { Link } from 'react-router-dom';
import styles from './KnowledgeDocumentNavigation.module.css';

const DocumentLink = ({ direction, document }) => (
  <Link
    className={`${styles.link} ${direction === 'next' ? styles.next : styles.previous}`}
    to={document.route}
    aria-label={`${direction === 'next' ? 'Siguiente' : 'Anterior'}: ${document.title}`}
  >
    <span>{direction === 'next' ? 'Siguiente' : 'Anterior'}</span>
    <strong>{document.title}</strong>
  </Link>
);

export const KnowledgeDocumentNavigation = ({ context }) => (
  <nav className={styles.navigation} aria-label="Navegación entre documentos">
    <p className={styles.position}>
      Documento {context.position} de {context.total} en {context.collection.title}
    </p>
    <div className={styles.links}>
      {context.previousDocument ? (
        <DocumentLink direction="previous" document={context.previousDocument} />
      ) : <span />}
      {context.nextDocument ? (
        <DocumentLink direction="next" document={context.nextDocument} />
      ) : null}
    </div>
  </nav>
);
