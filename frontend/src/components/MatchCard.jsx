import { Link } from 'react-router-dom';
import styles from './MatchCard.module.css';

export default function MatchCard({ match, entryNames = {}, translateStatus }) {
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

    return (
        <Link to={`/matches/${match.id}`} className={styles.link}>
            <div className={styles.card}>
                <div className={styles.header}>
                    <span>{match.scheduled_date ? new Date(match.scheduled_date).toLocaleString() : 'Fecha por determinar'}</span>
                    <span className={`${styles.statusBadge} ${isValidated ? styles.statusValidated : styles.statusPending}`}>
                        {translateStatus ? translateStatus(match.status) : match.status}
                    </span>
                </div>
                
                <div className={styles.participantRow}>
                    <div className={styles.participantName}>{homeName}</div>
                    <div className={`${styles.participantScore} ${isValidated ? styles.scoreValidated : styles.scorePending}`}>
                        {match.home_score !== null ? match.home_score : '-'}
                    </div>
                </div>
                
                <div className={styles.participantRow}>
                    <div className={styles.participantName}>{awayName}</div>
                    <div className={`${styles.participantScore} ${isValidated ? styles.scoreValidated : styles.scorePending}`}>
                        {match.away_score !== null ? match.away_score : '-'}
                    </div>
                </div>
            </div>
        </Link>
    );
}
