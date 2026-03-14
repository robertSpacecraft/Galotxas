import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();
    const { login, isAuthenticated } = useAuth();

    if (isAuthenticated) {
        return <Navigate to="/player" replace />;
    }

    const handleLogin = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await login(email, password);
            navigate('/player', { replace: true });
        } catch (err) {
            setError(err.response?.data?.message || 'Credenciales incorrectas o error de conexión');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page-container" style={{ maxWidth: '400px', margin: '4rem auto' }}>
            <h2 style={{ textAlign: 'center', marginBottom: '2rem' }}>Acceso Jugadores</h2>

            {error && <div style={{ color: '#ff7b72', marginBottom: '1.5rem', background: 'rgba(248,81,73,0.1)', padding: '0.8rem', borderRadius: '8px', border: '1px solid rgba(248,81,73,0.3)', fontSize: '0.9rem' }}>{error}</div>}

            <form onSubmit={handleLogin} style={{ display: 'flex', flexDirection: 'column', gap: '1.2rem' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: '0.5rem', color: 'var(--text-secondary)' }}>Correo Electrónico</label>
                    <input
                        type="email"
                        value={email} onChange={e => setEmail(e.target.value)}
                        required
                        style={{ width: '100%', padding: '0.8rem 1rem', borderRadius: '8px', border: '1px solid var(--surface-border)', background: 'rgba(0,0,0,0.2)', color: 'white', outline: 'none' }}
                        placeholder="tu@email.com"
                    />
                </div>
                <div>
                    <label style={{ display: 'block', marginBottom: '0.5rem', color: 'var(--text-secondary)' }}>Contraseña</label>
                    <input
                        type="password"
                        value={password} onChange={e => setPassword(e.target.value)}
                        required
                        style={{ width: '100%', padding: '0.8rem 1rem', borderRadius: '8px', border: '1px solid var(--surface-border)', background: 'rgba(0,0,0,0.2)', color: 'white', outline: 'none' }}
                        placeholder="••••••••"
                    />
                </div>

                <button type="submit" disabled={loading} className="card-hover" style={{
                    padding: '1rem',
                    borderRadius: '8px',
                    border: 'none',
                    background: 'var(--brand-gradient)',
                    color: 'white',
                    fontWeight: 'bold',
                    fontSize: '1rem',
                    opacity: loading ? 0.7 : 1,
                    marginTop: '1rem'
                }}>
                    {loading ? 'Accediendo...' : 'Iniciar Sesión'}
                </button>
            </form>
        </div>
    );
}