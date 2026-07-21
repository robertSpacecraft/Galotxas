import { Link } from 'react-router-dom';
import { knowledgeDocumentFragmentPath } from './knowledgeRoutes';
import styles from './KnowledgeTableOfContents.module.css';

const isInternalHeading = (heading) => (
  Number.isInteger(heading?.level)
  && heading.level >= 2
  && heading.level <= 6
  && typeof heading.id === 'string'
  && heading.id.length > 0
  && typeof heading.text === 'string'
  && heading.text.length > 0
);

export const KnowledgeTableOfContents = ({ document }) => {
  const headings = Array.isArray(document?.headings)
    ? document.headings.filter(isInternalHeading)
    : [];

  if (headings.length === 0) {
    return null;
  }

  const labelId = `${document.id.toLowerCase()}-contents-title`;

  return (
    <nav className={styles.container} aria-labelledby={labelId}>
      <p id={labelId} className={styles.title}>En este documento</p>
      <ol className={styles.list}>
        {headings.map((heading) => (
          <li
            key={`${heading.level}-${heading.id}`}
            className={styles[`level${heading.level}`]}
          >
            <Link
              to={knowledgeDocumentFragmentPath(document, heading.id)}
              state={{ focusKnowledgeFragment: true }}
            >
              {heading.text}
            </Link>
          </li>
        ))}
      </ol>
    </nav>
  );
};
