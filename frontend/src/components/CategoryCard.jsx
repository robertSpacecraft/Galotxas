import { Link } from 'react-router-dom';

export default function CategoryCard({ category }) {
    return (
        <Link to={`/categories/${category.id}/standings`} style={{ textDecoration: 'none' }}>
            <div className="card-hover" style={{
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid var(--surface-border)',
                borderRadius: '12px',
                padding: '1.5rem',
                color: 'var(--text-primary)'
            }}>
                <h4 style={{ fontSize: '1.2rem', marginBottom: '0.5rem' }}>{category.name}</h4>
                <p style={{ fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
                    Nivel {category.level || '-'} • {category.category_type}
                </p>
            </div>
        </Link>
    );
}
