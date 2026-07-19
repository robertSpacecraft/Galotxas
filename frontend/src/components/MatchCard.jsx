import { Link } from 'react-router-dom';
import { getMatchDetailPath } from '../navigation/competitionRoutes';
import styles from './MatchCard.module.css';

export default function MatchCard({
    match,
    entryNames = {},
    translateStatus,
    officialScoresOnly = false,
    showDetailLabel = false,
    showVenue = false,
}) {
    const getEntryName = (entry, directName, entryId) => {
        // Prioridad 1: Mapa de nombres enriquecido desde Standings
        if (entryId && entryNames[entryId]) return entryNames[entryId];
        
        // Prioridad 2: Nombre directo del objeto si existe
        if (directName) return directName;
        
        if (!entry) return 'Por determinar';
        
        if (entry.team) return entry.team.name || `Equipo #${entry.team.id}`;
        
        if (entry.player) {
            // Prioridad: Nickname > Nombre Real > ID fallback
            if (entry.player.nickname) return entry.player.nickname;
            
            const directFullName = `${entry.player.name || ''} ${entry.player.lastname || ''}`.trim();
            if (directFullName) {
                return directFullName;
            }

            if (entry.player.user) {
                const fullName = `${entry.player.user.name || ''} ${entry.player.user.lastname || ''}`.trim();
                if (fullName) return fullName;
            }
            
            return `Jugador #${entry.player.id}`;
        }
        
        return entry.name || 'Participante';
    };

    const homeEntry = match.home_entry || match.homeEntry;
    const awayEntry = match.away_entry || match.awayEntry;

    const homeName = getEntryName(homeEntry, match.home_team_name, match.home_entry_id || match.homeEntryId);
    const awayName = getEntryName(awayEntry, match.away_team_name, match.away_entry_id || match.awayEntryId);

    const isValidated = match.status === 'validated';
    const canShowScore = !officialScoresOnly || isValidated;
    const statusLabel = translateStatus
        ? translateStatus(match.status)
        : match.status || 'Estado por determinar';
    const scheduledDate = (() => {
        if (!match.scheduled_date) return 'Fecha por determinar';

        const date = new Date(match.scheduled_date);
        return Number.isNaN(date.getTime())
            ? 'Fecha por determinar'
            : new Intl.DateTimeFormat('es-ES', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
    })();
    const score = (value) => canShowScore && value !== null && value !== undefined ? value : '-';
    const detailPath = getMatchDetailPath(match.id);

    const card = (
        <div className={styles.card}>
            <div className={styles.header}>
                <span>{scheduledDate}</span>
                <span className={`${styles.statusBadge} ${isValidated ? styles.statusValidated : styles.statusPending}`}>
                    {statusLabel}
                </span>
            </div>

            <div className={styles.participantRow}>
                <div className={styles.participantName}>{homeName}</div>
                <div className={`${styles.participantScore} ${isValidated ? styles.scoreValidated : styles.scorePending}`}>
                    {score(match.home_score)}
                </div>
            </div>

            <div className={styles.participantRow}>
                <div className={styles.participantName}>{awayName}</div>
                <div className={`${styles.participantScore} ${isValidated ? styles.scoreValidated : styles.scorePending}`}>
                    {score(match.away_score)}
                </div>
            </div>

            {showVenue && (
                <div className={styles.venue}>Pista: {match.venue?.name || 'Por determinar'}</div>
            )}

            {showDetailLabel && (
                <span className={styles.detailLabel}>
                    {match.id ? 'Ver partido' : 'Detalle no disponible'}
                </span>
            )}
        </div>
    );

    if (!detailPath) {
        return <div className={styles.link}>{card}</div>;
    }

    return (
        <Link
            to={detailPath}
            className={styles.link}
            aria-label={`Ver partido: ${homeName} contra ${awayName}`}
        >
            {card}
        </Link>
    );
}
