import { Link } from 'react-router-dom';
import { learnPath, manualCollectionPath, manualPath } from './knowledgeRoutes';
import styles from './KnowledgeContextNavigation.module.css';

export const KnowledgeContextNavigation = ({ collection }) => (
  <nav className={styles.navigation} aria-label="Contexto del Manual">
    <span className={styles.label}>Estás en:</span>
    <ul>
      <li><Link to={learnPath()}>Aprende a jugar</Link></li>
      <li><Link to={manualPath()}>Manual</Link></li>
      <li><Link to={manualCollectionPath(collection.id)}>{collection.title}</Link></li>
    </ul>
  </nav>
);
