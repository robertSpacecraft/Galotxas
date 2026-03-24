import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import styles from './Login.module.css';

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
        <div className={`page-container ${styles.loginContainer}`}>
            <h2 className={styles.title}>Acceso Jugadores</h2>

            {error && <div className={styles.error}>{error}</div>}

            <form onSubmit={handleLogin} className={styles.form}>
                <div className={styles.fieldGroup}>
                    <label>Correo Electrónico</label>
                    <input
                        type="email"
                        value={email} onChange={e => setEmail(e.target.value)}
                        required
                        className={styles.input}
                        placeholder="tu@email.com"
                    />
                </div>
                <div className={styles.fieldGroup}>
                    <label>Contraseña</label>
                    <input
                        type="password"
                        value={password} onChange={e => setPassword(e.target.value)}
                        required
                        className={styles.input}
                        placeholder="••••••••"
                    />
                </div>

                <button type="submit" disabled={loading} className={styles.submitBtn}>
                    {loading ? 'Accediendo...' : 'Iniciar Sesión'}
                </button>
            </form>
        </div>
    );
}