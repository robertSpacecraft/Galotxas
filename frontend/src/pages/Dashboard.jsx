import { useState, useEffect } from 'react';
import { useLocation, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { meService } from '../api/me';
import MatchCard from '../components/MatchCard';
import styles from './Dashboard.module.css';

export default function Dashboard() {
    const { user, createPlayerProfile, refreshUser } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();
    
    // Auto-open registration if context came from tournament detail
    const [isRegistering, setIsRegistering] = useState(location.state?.action === 'createProfile');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const [registrations, setRegistrations] = useState([]);
    const [regsLoading, setRegsLoading] = useState(false);
    const [regsError, setRegsError] = useState(null);

    // New States
    const [activeTab, setActiveTab] = useState('resumen');
    
    // Matches
    const [matches, setMatches] = useState([]);
    const [matchesLoading, setMatchesLoading] = useState(false);
    const [matchesError, setMatchesError] = useState(null);

    // Calendar
    const [calendar, setCalendar] = useState([]);
    const [calendarLoading, setCalendarLoading] = useState(false);
    const [calendarError, setCalendarError] = useState(null);

    // Rankings
    const [rankings, setRankings] = useState([]);
    const [rankingsLoading, setRankingsLoading] = useState(false);
    const [rankingsError, setRankingsError] = useState(null);

    const isPlayer = !!user?.player;

    useEffect(() => {
        refreshUser().catch(err => console.error("Initial refresh failed", err));
    }, []);

    useEffect(() => {
        if (!isPlayer) return;

        if (activeTab === 'inscripciones' && registrations.length === 0) {
            setRegsLoading(true);
            meService.getRegistrations()
                .then(data => setRegistrations(data || []))
                .catch(err => setRegsError("No se pudieron cargar tus inscripciones en este momento."))
                .finally(() => setRegsLoading(false));
        }

        if (activeTab === 'partidos' && matches.length === 0) {
            setMatchesLoading(true);
            meService.getMatches()
                .then(data => setMatches(data || []))
                .catch(err => setMatchesError("No se pudieron cargar tus partidos en este momento."))
                .finally(() => setMatchesLoading(false));
        }

        if (activeTab === 'calendario' && calendar.length === 0) {
            setCalendarLoading(true);
            meService.getCalendar()
                .then(data => setCalendar(data || []))
                .catch(err => setCalendarError("No se pudo cargar tu calendario en este momento."))
                .finally(() => setCalendarLoading(false));
        }

        if (activeTab === 'rankings' && rankings.length === 0) {
            setRankingsLoading(true);
            meService.getRankings()
                .then(data => setRankings(data || []))
                .catch(err => setRankingsError("No se pudieron cargar tus rankings en este momento."))
                .finally(() => setRankingsLoading(false));
        }
    }, [isPlayer, activeTab]);

    const [playerData, setPlayerData] = useState({
        nickname: '',
        dni: '',
        birth_date: '',
        gender: '',
        level: '',
        license_number: '',
        dominant_hand: '',
        notes: ''
    });

    const handlePlayerChange = (e) => {
        const { name, value } = e.target;
        setPlayerData(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const preparedPlayerData = {
                ...playerData,
                level: playerData.level ? parseInt(playerData.level, 10) : null
            };

            const filteredPlayerProfile = Object.fromEntries(
                Object.entries(preparedPlayerData).filter(([_, v]) => v !== '' && v !== null)
            );
            
            console.log('Enviando actualización de perfil de jugador:', filteredPlayerProfile);
            await createPlayerProfile(filteredPlayerProfile);
            
            // Redirect back to tournament if we came from there
            if (location.state?.from) {
                navigate(location.state.from, { replace: true });
            } else {
                setIsRegistering(false);
            }
        } catch (err) {
            console.error(err);
            setError(err.response?.data?.message || 'Error al guardar el perfil de jugador. Verifica los datos introducidos.');
        } finally {
            setLoading(false);
        }
    };

    const renderCalendar = () => {
        if (calendarLoading) return <p style={{ padding: '1rem', color: 'var(--text-secondary)' }}>Cargando calendario...</p>;
        if (calendarError) return <p style={{ padding: '1rem', color: '#ff7b72' }}>{calendarError}</p>;
        if (!calendar || calendar.length === 0) return (
            <div className={styles.emptyState}>
                <p>No tienes eventos próximos en el calendario.</p>
            </div>
        );

        // Group by scheduled_date if the backend returns a flat list of matches
        // First check if it's already grouped (e.g. an object with dates as keys)
        let groupedCalendar = calendar;
        if (Array.isArray(calendar)) {
            groupedCalendar = calendar.reduce((acc, match) => {
                const dateKey = match.scheduled_date ? new Date(match.scheduled_date).toLocaleDateString() : 'Por programar';
                if (!acc[dateKey]) acc[dateKey] = [];
                acc[dateKey].push(match);
                return acc;
            }, {});
        }

        const dates = Object.keys(groupedCalendar).sort((a, b) => {
            if (a === 'Por programar') return 1;
            if (b === 'Por programar') return -1;
            return new Date(a) - new Date(b); // Sort chronologically
        });

        return (
            <div className={styles.tabContent}>
                <h2 className={styles.sectionTitle}>Mi Calendario</h2>
                {dates.map(date => (
                    <div key={date} className={styles.calendarDay}>
                        <h3 className={styles.calendarDateTitle}>{date}</h3>
                        <div className={styles.matchesGrid}>
                            {groupedCalendar[date].map(match => (
                                <MatchCard key={match.id} match={match} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <div className="page-container">
            <h1>Panel de Control</h1>

            {isPlayer && (
                <div className={styles.tabsContainer}>
                    <button className={`${styles.tabLink} ${activeTab === 'resumen' ? styles.activeTab : ''}`} onClick={() => setActiveTab('resumen')}>Resumen</button>
                    <button className={`${styles.tabLink} ${activeTab === 'inscripciones' ? styles.activeTab : ''}`} onClick={() => setActiveTab('inscripciones')}>Mis Inscripciones</button>
                    <button className={`${styles.tabLink} ${activeTab === 'partidos' ? styles.activeTab : ''}`} onClick={() => setActiveTab('partidos')}>Mis Partidos</button>
                    <button className={`${styles.tabLink} ${activeTab === 'calendario' ? styles.activeTab : ''}`} onClick={() => setActiveTab('calendario')}>Calendario</button>
                    <button className={`${styles.tabLink} ${activeTab === 'rankings' ? styles.activeTab : ''}`} onClick={() => setActiveTab('rankings')}>Rankings</button>
                </div>
            )}

            {(!isPlayer || activeTab === 'resumen') && (
                <div className={styles.tabContent}>

            <div className={styles.dashboardGrid}>
                {/* User Info Block */}
                <div className={styles.infoBlock}>
                    <h2 className={styles.sectionTitle}>Datos de Usuario</h2>
                    <div className={styles.profileCard}>
                        <div className={styles.infoField}>
                            <span className={styles.label}>Nombre y Apellidos</span>
                            <span className={styles.value}>{user.name} {user.lastname}</span>
                        </div>
                        <div className={styles.infoField}>
                            <span className={styles.label}>Correo Electrónico</span>
                            <span className={styles.value}>{user.email}</span>
                        </div>
                        <div className={styles.infoField}>
                            <span className={styles.label}>Rol de cuenta</span>
                            <span className={styles.value}>{user.role === 'admin' ? 'Administrador' : 'Usuario Estándar'}</span>
                        </div>
                    </div>
                    
                    {!isPlayer && !isRegistering && (
                        <div className={styles.ctaBox}>
                            <p>¿Quieres competir en torneos y aparecer en los rankings?</p>
                            <button className={styles.actionBtn} onClick={() => setIsRegistering(true)}>
                                Quiero ser jugador
                            </button>
                        </div>
                    )}
                </div>

                {/* Player Info Block */}
                {isPlayer && (
                    <div className={styles.infoBlock}>
                        <h2 className={styles.sectionTitle}>Perfil de Jugador</h2>
                        <div className={`${styles.profileCard} ${styles.playerCard}`}>
                            <div className={styles.infoField}>
                                <span className={styles.label}>Apodo (Nickname)</span>
                                <span className={styles.value}>{user.player.nickname || '-'}</span>
                            </div>
                            <div className={styles.infoField}>
                                <span className={styles.label}>DNI / NIE</span>
                                <span className={styles.value}>{user.player.dni || 'No proporcionado'}</span>
                            </div>
                            <div className={styles.infoField}>
                                <span className={styles.label}>Género</span>
                                <span className={styles.value}>{user.player.gender === 'male' ? 'Masculino' : user.player.gender === 'female' ? 'Femenino' : 'Otro'}</span>
                            </div>
                            <div className={styles.infoField}>
                                <span className={styles.label}>Nivel actual</span>
                                <span className={`${styles.value} ${styles.highlightValue}`}>{user.player.level || '0'}</span>
                            </div>
                            <div className={styles.infoField}>
                                <span className={styles.label}>Mano Dominante</span>
                                <span className={styles.value}>{user.player.dominant_hand === 'right' ? 'Diestro' : user.player.dominant_hand === 'left' ? 'Zurdo' : 'Ambidiestro'}</span>
                            </div>
                            <div className={styles.infoField}>
                                <span className={styles.label}>Nº Licencia</span>
                                <span className={styles.value}>{user.player.license_number || 'Sin licencia'}</span>
                            </div>
                            {user.player.notes && (
                                <div className={styles.infoFieldFull}>
                                    <span className={styles.label}>Notas / Observaciones</span>
                                    <span className={styles.notesValue}>{user.player.notes}</span>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {!isPlayer && isRegistering && (
                <div className={styles.playerFormSection}>
                    <h3 className={styles.formTitle}>Completar Perfil de Jugador</h3>
                    
                    {error && <div className={styles.errorMsg}>{error}</div>}

                    <form onSubmit={handleSubmit} className={styles.form}>
                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label>Apodo (Nickname)</label>
                                <input
                                    type="text"
                                    name="nickname"
                                    value={playerData.nickname}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                    placeholder="Tu apodo en la pista"
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label>DNI / NIE {playerData.birth_date && (new Date().getFullYear() - new Date(playerData.birth_date).getFullYear() >= 18) && '*'}</label>
                                <input
                                    type="text"
                                    name="dni"
                                    value={playerData.dni}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                    placeholder="12345678X"
                                />
                            </div>
                        </div>

                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label>Fecha de Nacimiento</label>
                                <input
                                    type="date"
                                    name="birth_date"
                                    value={playerData.birth_date}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label>Género</label>
                                <select 
                                    name="gender" 
                                    value={playerData.gender} 
                                    onChange={handlePlayerChange}
                                    className={styles.select}
                                >
                                    <option value="">Selecciona...</option>
                                    <option value="male">Masculino</option>
                                    <option value="female">Femenino</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                        </div>

                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label>Nivel de juego (1-10) *</label>
                                <input
                                    type="number"
                                    name="level"
                                    min="1"
                                    max="10"
                                    value={playerData.level}
                                    onChange={handlePlayerChange}
                                    required
                                    className={styles.input}
                                    placeholder="Tu nivel"
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label>Nº Licencia</label>
                                <input
                                    type="text"
                                    name="license_number"
                                    value={playerData.license_number}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                    placeholder="Opcional"
                                />
                            </div>
                        </div>

                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label>Mano Dominante</label>
                                <select 
                                    name="dominant_hand" 
                                    value={playerData.dominant_hand} 
                                    onChange={handlePlayerChange}
                                    className={styles.select}
                                >
                                    <option value="">Selecciona...</option>
                                    <option value="right">Diestro</option>
                                    <option value="left">Zurdo</option>
                                    <option value="both">Ambidiestro</option>
                                </select>
                            </div>
                        </div>

                        <div className={styles.fieldGroup}>
                            <label>Notas / Observaciones</label>
                            <textarea
                                name="notes"
                                value={playerData.notes}
                                onChange={handlePlayerChange}
                                className={styles.textarea}
                                placeholder="Algo que debamos saber..."
                            />
                        </div>

                        <button 
                            type="submit" 
                            disabled={loading} 
                            className={styles.submitBtn}
                        >
                            {loading ? 'Guardando...' : 'Convertirme en Jugador'}
                        </button>
                        
                        <button 
                            type="button" 
                            disabled={loading} 
                            onClick={() => setIsRegistering(false)} 
                            className={styles.cancelBtn}
                        >
                            Cancelar
                        </button>
                    </form>
                </div>
            )}
                </div>
            )}

            {isPlayer && activeTab === 'inscripciones' && (
                <div className={styles.tabContent}>
                    <h2 className={styles.sectionTitle}>Mis Inscripciones a Campeonatos</h2>
                    {regsLoading ? (
                        <p style={{ padding: '1rem', color: 'var(--text-secondary)' }}>Cargando inscripciones...</p>
                    ) : regsError ? (
                        <p style={{ padding: '1rem', color: '#ff7b72' }}>{regsError}</p>
                    ) : registrations.length > 0 ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                            {registrations.map(reg => (
                                <div key={reg.id} style={{ padding: '1rem', background: 'rgba(255,255,255,0.02)', borderRadius: '8px', border: '1px solid var(--surface-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
                                    <div>
                                        <h3 style={{ margin: '0 0 0.5rem 0', fontSize: '1.1rem', color: 'white' }}>{reg.championship?.name || 'Torneo'}</h3>
                                        <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
                                            Estado de la solicitud: <strong style={{ color: reg.status === 'registered' ? '#4ade80' : 'var(--brand-primary)', textTransform: 'capitalize' }}>{reg.status}</strong>
                                        </p>
                                    </div>
                                    <Link to={`/torneos/${reg.championship_id}`} style={{ padding: '0.6rem 1.2rem', background: 'var(--brand-primary)', color: 'white', textDecoration: 'none', borderRadius: '6px', fontSize: '0.9rem', fontWeight: 'bold', transition: 'all 0.2s' }}>
                                        Ver torneo
                                    </Link>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className={styles.emptyState}>
                            <p>Aún no te has inscrito a ningún torneo.</p>
                            <Link to="/torneos" className={styles.actionBtn} style={{ display: 'inline-block', textDecoration: 'none' }}>Ver Torneos Disponibles</Link>
                        </div>
                    )}
                </div>
            )}

            {isPlayer && activeTab === 'partidos' && (
                <div className={styles.tabContent}>
                    <h2 className={styles.sectionTitle}>Mis Partidos</h2>
                    {matchesLoading ? (
                        <p style={{ padding: '1rem', color: 'var(--text-secondary)' }}>Cargando partidos...</p>
                    ) : matchesError ? (
                        <p style={{ padding: '1rem', color: '#ff7b72' }}>{matchesError}</p>
                    ) : matches.length > 0 ? (
                        <div className={styles.matchesGrid}>
                            {matches.map(match => (
                                <MatchCard key={match.id} match={match} />
                            ))}
                        </div>
                    ) : (
                        <div className={styles.emptyState}>
                            <p>No tienes partidos registrados todavía.</p>
                        </div>
                    )}
                </div>
            )}

            {isPlayer && activeTab === 'calendario' && renderCalendar()}

            {isPlayer && activeTab === 'rankings' && (
                <div className={styles.tabContent}>
                    <h2 className={styles.sectionTitle}>Posición en Rankings</h2>
                    {rankingsLoading ? (
                        <p style={{ padding: '1rem', color: 'var(--text-secondary)' }}>Cargando rankings...</p>
                    ) : rankingsError ? (
                        <p style={{ padding: '1rem', color: '#ff7b72' }}>{rankingsError}</p>
                    ) : rankings.length > 0 ? (
                        <table className={styles.minimalTable}>
                            <thead>
                                <tr>
                                    <th>Pos</th>
                                    <th>Nombre (Equipo o Jugador)</th>
                                    <th>Competición / Categoría</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PP</th>
                                    <th>Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rankings.map((rk, i) => (
                                    <tr key={i}>
                                        <td><span style={{ background: 'var(--brand-primary)', color: 'white', padding: '0.2rem 0.6rem', borderRadius: '4px', fontWeight: 'bold' }}>{rk.position || '-'}</span></td>
                                        <td>
                                            <div style={{ fontWeight: 'bold', color: 'white' }}>{rk.entry_name || '-'}</div>
                                            <div style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>{rk.entry_type === 'team' ? 'Equipo' : 'Individual'}</div>
                                        </td>
                                        <td>
                                            <div style={{ fontWeight: 'bold', color: '#e2e8f0' }}>{rk.championship || '-'}</div>
                                            <div style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>{rk.category || '-'}</div>
                                        </td>
                                        <td>{rk.played !== undefined ? rk.played : '-'}</td>
                                        <td>{rk.wins !== undefined ? rk.wins : '-'}</td>
                                        <td>{rk.losses !== undefined ? rk.losses : '-'}</td>
                                        <td><strong style={{ color: '#4ade80' }}>{rk.points !== undefined ? rk.points : '-'}</strong></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className={styles.emptyState}>
                            <p>No tienes datos de ranking registrados todavía en tus categorías activas.</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}