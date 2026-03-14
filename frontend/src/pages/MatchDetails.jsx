import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../api/client';

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

    if (loading) return <div className="page-container"><p>Cargando detalles...</p></div>;
    if (!match) return <div className="page-container"><p>Partido no encontrado.</p></div>;

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

    return (
        <div className="page-container">
            <div style={{ marginBottom: '2rem' }}>
               <Link to={`/categories/${match.round?.category_id}/schedule`} style={{ color: 'var(--brand-primary)', textDecoration: 'none' }}>
                    &larr; Volver al calendario
               </Link>
            </div>

            <div style={{ textAlign: 'center', marginBottom: '3rem' }}>
                <div style={{ display: 'inline-block', background: 'rgba(255,255,255,0.05)', padding: '6px 16px', borderRadius: '20px', fontSize: '0.9rem', color: 'var(--text-secondary)', marginBottom: '1.5rem' }}>
                    {match.round?.name} • {match.scheduled_date ? new Date(match.scheduled_date).toLocaleString() : 'Fecha sin definir'}
                </div>
                
                <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '2rem' }}>
                    <div style={{ flex: 1, textAlign: 'right', fontSize: '1.5rem', fontWeight: 'bold' }}>
                        {match.home_team_name}
                    </div>
                    
                    <div style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'center', 
                        gap: '1rem', 
                        background: 'rgba(255,255,255,0.03)', 
                        padding: '1rem 2rem', 
                        borderRadius: '16px',
                        border: '1px solid var(--surface-border)'
                    }}>
                        <span style={{ fontSize: '3rem', fontWeight: '900', color: match.status === 'validated' ? 'var(--brand-primary)' : 'var(--text-secondary)' }}>
                            {match.home_score !== null ? match.home_score : '-'}
                        </span>
                        <span style={{ color: 'var(--text-secondary)' }}>vs</span>
                        <span style={{ fontSize: '3rem', fontWeight: '900', color: match.status === 'validated' ? 'var(--brand-primary)' : 'var(--text-secondary)' }}>
                            {match.away_score !== null ? match.away_score : '-'}
                        </span>
                    </div>

                    <div style={{ flex: 1, textAlign: 'left', fontSize: '1.5rem', fontWeight: 'bold' }}>
                        {match.away_team_name}
                    </div>
                </div>

                <div style={{ marginTop: '2rem' }}>
                    <span style={{ 
                        background: match.status === 'validated' ? 'var(--success)' : 'rgba(255,255,255,0.1)',
                        color: match.status === 'validated' ? '#fff' : 'inherit',
                        padding: '6px 16px', borderRadius: '20px', fontSize: '0.9rem', fontWeight: 'bold' 
                    }}>
                        {translateStatus(match.status)}
                    </span>
                </div>
            </div>

            {user && match.status === 'scheduled' && (
                <div style={{ 
                    background: 'rgba(56, 139, 253, 0.1)', 
                    borderRadius: '12px', 
                    padding: '2rem', 
                    border: '1px solid var(--brand-secondary)',
                    marginBottom: '2rem'
                }}>
                    <h3 style={{ marginBottom: '1rem', color: 'var(--brand-primary)' }}>Introducir Resultado</h3>
                    <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>Como jugador autenticado puedes registrar el marcador final de este partido.</p>
                    
                    {submitError && <div style={{ color: '#ff7b72', marginBottom: '1.5rem', background: 'rgba(248,81,73,0.1)', padding: '0.8rem', borderRadius: '8px', border: '1px solid rgba(248,81,73,0.3)', fontSize: '0.9rem' }}>{submitError}</div>}
                    
                    <form onSubmit={handleResultSubmit} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '1.5rem', alignItems: 'end' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', color: 'var(--text-secondary)', fontSize: '0.9rem' }}>Juegos {match.home_team_name}</label>
                            <input 
                                type="number" min="0" max="15" 
                                value={homeScore} onChange={e => setHomeScore(e.target.value)} 
                                required 
                                style={{ width: '100%', padding: '0.8rem', borderRadius: '8px', border: '1px solid var(--surface-border)', background: 'rgba(0,0,0,0.3)', color: 'white', fontSize: '1.2rem', textAlign: 'center' }}
                            />
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', color: 'var(--text-secondary)', fontSize: '0.9rem' }}>Juegos {match.away_team_name}</label>
                            <input 
                                type="number" min="0" max="15" 
                                value={awayScore} onChange={e => setAwayScore(e.target.value)} 
                                required 
                                style={{ width: '100%', padding: '0.8rem', borderRadius: '8px', border: '1px solid var(--surface-border)', background: 'rgba(0,0,0,0.3)', color: 'white', fontSize: '1.2rem', textAlign: 'center' }}
                            />
                        </div>
                        <button type="submit" disabled={submitLoading || homeScore === '' || awayScore === ''} style={{ 
                            padding: '0.8rem', 
                            borderRadius: '8px', 
                            border: 'none', 
                            background: 'var(--brand-gradient)', 
                            color: 'white', 
                            fontWeight: 'bold', 
                            cursor: (submitLoading || homeScore === '' || awayScore === '') ? 'not-allowed' : 'pointer',
                            opacity: (submitLoading || homeScore === '' || awayScore === '') ? 0.5 : 1,
                            height: '50px'
                        }}>
                            {submitLoading ? 'Enviando...' : 'Enviar Resultado'}
                        </button>
                    </form>
                </div>
            )}

            {user?.role === 'admin' && match.status === 'submitted' && (
                <div style={{ 
                    background: 'rgba(46, 160, 67, 0.1)', 
                    borderRadius: '12px', 
                    padding: '2rem', 
                    border: '1px solid var(--success)',
                    marginBottom: '2rem',
                    textAlign: 'center'
                }}>
                    <h3 style={{ marginBottom: '1rem', color: 'var(--success)' }}>Validación de Administración</h3>
                    <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>Este resultado ha sido introducido y está pendiente de validación oficial.</p>
                    
                    {submitError && <div style={{ color: '#ff7b72', marginBottom: '1.5rem', background: 'rgba(248,81,73,0.1)', padding: '0.8rem', borderRadius: '8px', border: '1px solid rgba(248,81,73,0.3)', fontSize: '0.9rem' }}>{submitError}</div>}
                    
                    <button onClick={handleValidateResult} disabled={submitLoading} style={{ 
                        padding: '1rem 2rem', 
                        borderRadius: '8px', 
                        border: 'none', 
                        background: 'var(--success)', 
                        color: 'white', 
                        fontWeight: 'bold', 
                        cursor: submitLoading ? 'not-allowed' : 'pointer',
                        opacity: submitLoading ? 0.7 : 1,
                        fontSize: '1.1rem'
                    }}>
                        {submitLoading ? 'Validando...' : 'Aprobar Resultado Definitivo'}
                    </button>
                </div>
            )}

            <div style={{ 
                background: 'rgba(0,0,0,0.2)', 
                borderRadius: '12px', 
                padding: '2rem', 
                border: '1px solid var(--surface-border)' 
            }}>
                <h3 style={{ marginBottom: '1.5rem', color: 'var(--text-secondary)' }}>Detalles de la partida</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                    <div>
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', marginBottom: '0.25rem' }}>Instalación</div>
                        <div style={{ fontWeight: '500' }}>{match.venue?.name || 'No especificada'}</div>
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>{match.venue?.location}</div>
                    </div>
                    <div>
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', marginBottom: '0.25rem' }}>Categoría</div>
                        <div style={{ fontWeight: '500' }}>{match.round?.category?.name || 'Desconocida'}</div>
                    </div>
                    {match.submitted_by && (
                        <div>
                            <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', marginBottom: '0.25rem' }}>Resultado enviado por</div>
                            <div style={{ fontWeight: '500' }}>{match.submitted_by_user?.name || `Usuario ID: ${match.submitted_by}`}</div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
