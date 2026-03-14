import { Link } from 'react-router-dom';

export default function MatchCard({ match, translateStatus }) {
    return (
        <Link to={`/matches/${match.id}`} style={{ textDecoration: 'none' }}>
            <div className="card-hover" style={{
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid var(--surface-border)',
                borderRadius: '12px',
                padding: '1.5rem',
                color: 'var(--text-primary)',
                display: 'flex',
                flexDirection: 'column',
                gap: '1rem'
            }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', color: 'var(--text-secondary)' }}>
                    <span>{match.scheduled_date ? new Date(match.scheduled_date).toLocaleString() : 'Fecha por determinar'}</span>
                    <span style={{ 
                        background: match.status === 'validated' ? 'var(--success)' : 'rgba(255,255,255,0.1)',
                        color: match.status === 'validated' ? '#fff' : 'inherit',
                        padding: '2px 8px', borderRadius: '12px', fontSize: '0.75rem', fontWeight: 'bold' 
                    }}>
                        {translateStatus ? translateStatus(match.status) : match.status}
                    </span>
                </div>
                
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ flex: 1, fontWeight: '600', fontSize: '1.1rem' }}>{match.home_team_name}</div>
                    <div style={{ width: '60px', textAlign: 'center', fontSize: '1.4rem', fontWeight: '800', color: match.status === 'validated' ? 'var(--brand-primary)' : 'var(--text-secondary)' }}>
                        {match.home_score !== null ? match.home_score : '-'}
                    </div>
                </div>
                
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ flex: 1, fontWeight: '600', fontSize: '1.1rem' }}>{match.away_team_name}</div>
                    <div style={{ width: '60px', textAlign: 'center', fontSize: '1.4rem', fontWeight: '800', color: match.status === 'validated' ? 'var(--brand-primary)' : 'var(--text-secondary)' }}>
                        {match.away_score !== null ? match.away_score : '-'}
                    </div>
                </div>
            </div>
        </Link>
    );
}
