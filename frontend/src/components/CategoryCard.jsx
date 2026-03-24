import { Link } from 'react-router-dom';
import styles from './CategoryCard.module.css';

export default function CategoryCard({ category }) {
    return (
        <Link to={`/categories/${category.id}/standings`} className={styles.link}>
            <div className={styles.card}>
                <h4 className={styles.title}>{category.name}</h4>
                <p className={styles.details}>
                    Nivel {category.level || '-'} • {category.category_type}
                </p>
            </div>
        </Link>
    );
}
