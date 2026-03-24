import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../api/client';
import styles from './MatchDetails.module.css';

export default function MatchDetails() {
    const { matchId } = useParams();
    const { user, token } = useAuth();
    
    const [submitLoading, setSubmitLoading] = useState(false);
    const [homeScore, setHomeScore] = useState('');
    const [awayScore, setAwayScore] = useState('');
    const [submitError, setSubmitError] = useState(null);
    
    const [match, setMatch] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchMatch = () => {
        api.get(`/matches/${matchId}`)
            .then(response => {
                setMatch(response.data.data);
                setLoading(false);
            })
            .catch(error => {
                console.error(error);
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchMatch();
    }, [matchId]);

    const handleResultSubmit = async (e) => {
        e.preventDefault();
        if (!token) return;
        
        setSubmitLoading(true);
        setSubmitError(null);
        
        try {
            await api.post(`/matches/${matchId}/submit-result`, {
                home_score: parseInt(homeScore),
                away_score: parseInt(awayScore)
            });
            // Refresh match data
            fetchMatch();
        } catch (err) {
            setSubmitError(err.response?.data?.message || 'Error enviando el resultado');
        } finally {
            setSubmitLoading(false);
        }
    };

    const handleValidateResult = async () => {
        if (!token || user?.role !== 'admin') return;
        
        setSubmitLoading(true);
        setSubmitError(null);
        
        try {
            await api.post(`/admin/matches/${matchId}/validate-result`);
            fetchMatch();
        } catch (err) {
            setSubmitError(err.response?.data?.message || 'Error validando el resultado');
        } finally {
            setSubmitLoading(false);
        }
    };

    if (loading) return <div className={styles.container}><p>Cargando detalles...</p></div>;
    if (!match) return <div className={styles.container}><p>Partido no encontrado.</p></div>;

    const translateStatus = (status) => {
        const statuses = {
             'scheduled': 'Programado',
             'submitted': 'Pendiente de Validar',
             'validated': 'Finalizado',
             'postponed': 'Aplazado',
             'cancelled': 'Cancelado'
        };
        return statuses[status] || status;
    };

    const getEntryName = (entry) => {
        if (!entry) return 'Por determinar';
        if (entry.team) return entry.team.name;
        if (entry.player) {
            if (entry.player.nickname) return entry.player.nickname;
            const fullName = `${entry.player.name || ''} ${entry.player.lastname || ''}`.trim();
            return fullName || `Jugador #${entry.player.id}`;
        }
        return 'Participante';
    };

    return (
        <div className={styles.container}>
            <div className={styles.spacingBottom}>
               <Link to={`/categories/${match.round?.category?.id || match.round?.category_id}`} className={styles.backLink}>
                    &larr; Volver al calendario
               </Link>
            </div>

            <div className={styles.header}>
                <div className={styles.matchMeta}>
                    {match.round?.name} • {match.scheduled_date ? new Date(match.scheduled_date).toLocaleString() : 'Fecha sin definir'}
                </div>
                
                <div className={styles.scoreboard}>
                    <div className={`${styles.teamName} ${styles.homeTeam}`}>
                        {getEntryName(match.home_entry)}
                    </div>
                    
                    <div className={styles.scoreBox}>
                        <span className={`${styles.score} ${match.status === 'validated' ? styles.scoreValidated : styles.scorePending}`}>
                            {match.home_score !== null ? match.home_score : '-'}
                        </span>
                        <span className={styles.vs}>vs</span>
                        <span className={`${styles.score} ${match.status === 'validated' ? styles.scoreValidated : styles.scorePending}`}>
                            {match.away_score !== null ? match.away_score : '-'}
                        </span>
                    </div>

                    <div className={`${styles.teamName} ${styles.awayTeam}`}>
                        {getEntryName(match.away_entry)}
                    </div>
                </div>

                <div className={styles.statusContainer}>
                    <span className={`${styles.statusBadge} ${match.status === 'validated' ? styles.statusValidated : styles.statusDefault}`}>
                        {translateStatus(match.status)}
                    </span>
                </div>
            </div>

            {user && match.status === 'scheduled' && (
                <div className={styles.actionPanel}>
                    <h3 className={styles.panelTitle}>Introducir Resultado</h3>
                    <p className={styles.panelSubtitle}>Como jugador autenticado puedes registrar el marcador final de este partido.</p>
                    
                    {submitError && <div className={styles.error}>{submitError}</div>}
                    
                    <form onSubmit={handleResultSubmit} className={styles.submitForm}>
                        <div className={styles.fieldGroup}>
                            <label>Juegos {getEntryName(match.home_entry)}</label>
                            <input 
                                type="number" min="0" max="15" 
                                value={homeScore} onChange={e => setHomeScore(e.target.value)} 
                                required 
                                className={styles.scoreInput}
                            />
                        </div>
                        <div className={styles.fieldGroup}>
                            <label>Juegos {getEntryName(match.away_entry)}</label>
                            <input 
                                type="number" min="0" max="15" 
                                value={awayScore} onChange={e => setAwayScore(e.target.value)} 
                                required 
                                className={styles.scoreInput}
                            />
                        </div>
                        <button type="submit" disabled={submitLoading || homeScore === '' || awayScore === ''} className={styles.submitBtn}>
                            {submitLoading ? 'Enviando...' : 'Enviar Resultado'}
                        </button>
                    </form>
                </div>
            )}

            {user?.role === 'admin' && match.status === 'submitted' && (
                <div className={styles.adminPanel}>
                    <h3 className={`${styles.panelTitle} ${styles.adminTitle}`}>Validación de Administración</h3>
                    <p className={styles.panelSubtitle}>Este resultado ha sido introducido y está pendiente de validación oficial.</p>
                    
                    {submitError && <div className={styles.error}>{submitError}</div>}
                    
                    <button onClick={handleValidateResult} disabled={submitLoading} className={styles.adminBtn}>
                        {submitLoading ? 'Validando...' : 'Aprobar Resultado Definitivo'}
                    </button>
                </div>
            )}

            <div className={styles.detailsSection}>
                <h3 className={styles.sectionTitle}>Detalles de la partida</h3>
                <div className={styles.detailsGrid}>
                    <div>
                        <div className={styles.detailItemLabel}>Pista</div>
                        <div className={styles.detailItemValue}>{match.venue?.name || 'No especificada'}</div>
                        <div className={styles.detailItemSubValue}>{match.venue?.location}</div>
                    </div>
                    <div>
                        <div className={styles.detailItemLabel}>Categoría</div>
                        <div className={styles.detailItemValue}>{match.round?.category?.name || 'Desconocida'}</div>
                    </div>
                    {match.submitted_by && (
                        <div>
                            <div className={styles.detailItemLabel}>Resultado enviado por</div>
                            <div className={styles.detailItemValue}>{match.submitted_by_user?.name || `Usuario ID: ${match.submitted_by}`}</div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
