import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { MatchWorkflow } from '../components/MatchWorkflow/MatchWorkflow';
import { matchesService } from '../api/matches';
import { useAuth } from '../hooks/useAuth';
import styles from './MatchDetails.module.css';

const statusLabels = {
    scheduled: 'Programado',
    submitted: 'Pendiente de confirmación',
    validated: 'Finalizado',
    under_review: 'En revisión',
    postponed: 'Aplazado',
    cancelled: 'Cancelado',
};

const getStatusLabel = (status) => statusLabels[status] || status || 'Desconocido';

const getEntryName = (entry) => {
    if (!entry) {
        return 'Por determinar';
    }

    if (entry.team) {
        return entry.team.name || `Equipo #${entry.team.id}`;
    }

    if (entry.player) {
        return entry.player.nickname
            || `${entry.player.name || ''} ${entry.player.lastname || ''}`.trim()
            || `Jugador #${entry.player.id}`;
    }

    return 'Participante';
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Fecha sin definir';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const scoreValue = (value) => value ?? '-';

export default function MatchDetails() {
    const { matchId } = useParams();
    const { token } = useAuth();

    const [match, setMatch] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchMatch = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const data = await matchesService.getMatch(matchId);
            setMatch(data);
        } catch (err) {
            setError(err.response?.data?.message || 'No se ha podido cargar el partido.');
            setMatch(null);
        } finally {
            setLoading(false);
        }
    }, [matchId]);

    useEffect(() => {
        fetchMatch();
    }, [fetchMatch]);

    const handleWorkflowMatchChange = useCallback((updatedMatch) => {
        setMatch(updatedMatch);
    }, []);

    if (loading) {
        return (
            <div className={styles.container}>
                <p className={styles.stateMessage}>Cargando detalles...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className={styles.container}>
                <p className={styles.error}>{error}</p>
            </div>
        );
    }

    if (!match) {
        return (
            <div className={styles.container}>
                <p className={styles.stateMessage}>Partido no encontrado.</p>
            </div>
        );
    }

    const category = match.round?.category;
    const championship = category?.championship;
    const backTarget = category?.id ? `/categories/${category.id}` : '/torneos';
    const homeName = getEntryName(match.home_entry);
    const awayName = getEntryName(match.away_entry);

    return (
        <div className={styles.container}>
            <h1 className={styles.visuallyHidden}>Partido: {homeName} contra {awayName}</h1>

            <div className={styles.spacingBottom}>
                <Link to={backTarget} className={styles.backLink}>
                    Volver al calendario
                </Link>
            </div>

            <header className={styles.header}>
                <div className={styles.matchMeta}>
                    {championship?.name || 'Campeonato'} · {category?.name || 'Categoría'} · {match.round?.name || 'Jornada'}
                </div>

                <div className={styles.scoreboard}>
                    <div className={`${styles.teamName} ${styles.homeTeam}`}>
                        {homeName}
                    </div>

                    <div className={styles.scoreBox} aria-label={`${homeName} contra ${awayName}`}>
                        <span className={`${styles.score} ${match.status === 'validated' ? styles.scoreValidated : styles.scorePending}`}>
                            {scoreValue(match.home_score)}
                        </span>
                        <span className={styles.vs}>vs</span>
                        <span className={`${styles.score} ${match.status === 'validated' ? styles.scoreValidated : styles.scorePending}`}>
                            {scoreValue(match.away_score)}
                        </span>
                    </div>

                    <div className={`${styles.teamName} ${styles.awayTeam}`}>
                        {awayName}
                    </div>
                </div>

                <div className={styles.statusContainer}>
                    <span className={`${styles.statusBadge} ${styles[`status_${match.status}`] || styles.statusDefault}`}>
                        {getStatusLabel(match.status)}
                    </span>
                </div>
            </header>

            {token ? (
                <MatchWorkflow matchId={matchId} onMatchChange={handleWorkflowMatchChange} />
            ) : (
                <section className={styles.workflowPrompt}>
                    <h2>Gestión del resultado</h2>
                    <p>
                        Inicia sesión como participante para enviar o confirmar el resultado de este partido.
                    </p>
                    <Link to="/login" className={styles.loginLink}>Iniciar sesión</Link>
                </section>
            )}

            <section className={styles.detailsSection}>
                <h2 className={styles.sectionTitle}>Detalles de la partida</h2>
                <div className={styles.detailsGrid}>
                    <div>
                        <div className={styles.detailItemLabel}>Fecha</div>
                        <div className={styles.detailItemValue}>{formatDateTime(match.scheduled_date)}</div>
                    </div>
                    <div>
                        <div className={styles.detailItemLabel}>Pista</div>
                        <div className={styles.detailItemValue}>{match.venue?.name || 'No especificada'}</div>
                    </div>
                    <div>
                        <div className={styles.detailItemLabel}>Categoría</div>
                        <div className={styles.detailItemValue}>{category?.name || 'Desconocida'}</div>
                    </div>
                    <div>
                        <div className={styles.detailItemLabel}>Campeonato</div>
                        <div className={styles.detailItemValue}>{championship?.name || 'Desconocido'}</div>
                        {championship?.season?.name ? (
                            <div className={styles.detailItemSubValue}>{championship.season.name}</div>
                        ) : null}
                    </div>
                </div>
            </section>
        </div>
    );
}
