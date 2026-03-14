import { useAuth } from '../context/AuthContext';

export default function Dashboard() {
    const { user } = useAuth();

    return (
        <div className="page-container">
            <h1>Mi panel de jugador</h1>

            <div style={{
                background: 'rgba(255,255,255,0.02)',
                padding: '2rem',
                borderRadius: '12px',
                border: '1px solid var(--surface-border)',
                display: 'grid',
                gap: '1rem',
                marginTop: '2rem'
            }}>
                <div>
                    <span style={{ color: 'var(--text-secondary)', display: 'block', fontSize: '0.85rem' }}>
                        Nombre
                    </span>
                    <span style={{ fontSize: '1.2rem', fontWeight: '500' }}>
                        {user.name}
                    </span>
                </div>

                <div>
                    <span style={{ color: 'var(--text-secondary)', display: 'block', fontSize: '0.85rem' }}>
                        Email
                    </span>
                    <span style={{ fontSize: '1.2rem', fontWeight: '500' }}>
                        {user.email}
                    </span>
                </div>

                <div>
                    <span style={{ color: 'var(--text-secondary)', display: 'block', fontSize: '0.85rem' }}>
                        Perfil de jugador
                    </span>
                    <span style={{
                        fontSize: '1.2rem',
                        fontWeight: '500',
                        color: user.player ? 'var(--success)' : 'var(--text-secondary)'
                    }}>
                        {user.player ? user.player.name : 'No vinculado a jugador'}
                    </span>
                </div>
            </div>
        </div>
    );
}