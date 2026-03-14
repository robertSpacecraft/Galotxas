import { useState, useEffect } from 'react';
import api from '../api/client';
import CategoryCard from '../components/CategoryCard';

export default function Home() {
    const [seasons, setSeasons] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/seasons')
            .then(response => {
                setSeasons(response.data);
                setLoading(false);
            })
            .catch(error => {
                console.error("Error fetching seasons", error);
                setLoading(false);
            });
    }, []);

    if (loading) return <div className="page-container"><p>Cargando temporadas...</p></div>;

    return (
        <div className="page-container">
            <h1>Competiciones Galotxas</h1>
            <p>Selecciona una categoría para ver clasificaciones y resultados.</p>

            {seasons.map(season => (
                <div key={season.id} className="season-block" style={{ marginTop: '2rem' }}>
                    <h2 style={{ borderBottom: '1px solid var(--surface-border)', paddingBottom: '0.5rem', marginBottom: '1rem' }}>
                        {season.name}
                    </h2>
                    
                    {season.championships?.map(championship => (
                        <div key={championship.id} className="championship-block" style={{ marginBottom: '2rem' }}>
                            <h3 style={{ color: 'var(--brand-primary)', marginBottom: '1rem' }}>{championship.name}</h3>
                            
                            <div className="category-grid" style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
                                gap: '1.5rem'
                            }}>
                                {championship.categories?.map(category => (
                                    <CategoryCard key={category.id} category={category} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            ))}
            
            {seasons.length === 0 && <p style={{ marginTop: '2rem' }}>No hay competiciones activas en este momento.</p>}
        </div>
    );
}
