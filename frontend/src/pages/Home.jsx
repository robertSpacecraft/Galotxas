import { useState, useEffect } from 'react';
import api from '../api/client';
import CategoryCard from '../components/CategoryCard';
import styles from './Home.module.css';

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
                <div key={season.id} className={styles.seasonBlock}>
                    <h2 className={styles.seasonTitle}>
                        {season.name}
                    </h2>
                    
                    {season.championships?.map(championship => (
                        <div key={championship.id} className={styles.championshipBlock}>
                            <h3 className={styles.championshipTitle}>{championship.name}</h3>
                            
                            <div className={styles.categoryGrid}>
                                {championship.categories?.map(category => (
                                    <CategoryCard key={category.id} category={category} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            ))}
            
            {seasons.length === 0 && <p className={styles.noSeasons}>No hay competiciones activas en este momento.</p>}
        </div>
    );
}
