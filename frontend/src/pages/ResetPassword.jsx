import { useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import styles from './AuthForms.module.css';

export default function ResetPassword() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { resetPassword } = useAuth();

    const email = searchParams.get('email') || '';
    const token = searchParams.get('token') || '';

    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (password !== passwordConfirmation) {
            setError('Las contraseñas no coinciden');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            await resetPassword({
                token,
                email,
                password,
                password_confirmation: passwordConfirmation
            });
            setSuccess(true);
            setTimeout(() => {
                navigate('/login');
            }, 3000);
        } catch (err) {
            setError(err.response?.data?.message || 'Error al restablecer la contraseña.');
        } finally {
            setLoading(false);
        }
    };

    if (!token || !email) {
        return (
            <div className={`page-container ${styles.container}`}>
                <h2 className={styles.title}>Enlace Inválido</h2>
                <div className={styles.errorMsg}>Este enlace de recuperación no es válido o ha expirado.</div>
                <div className={styles.backLink}>
                    <Link to="/forgot-password" className={styles.link}>Solicitar uno nuevo</Link>
                </div>
            </div>
        );
    }

    if (success) {
        return (
            <div className={`page-container ${styles.container}`}>
                <h2 className={styles.title}>¡Completado!</h2>
                <div className={styles.successMsg}>Tu contraseña ha sido restablecida correctamente. Redirigiendo al inicio de sesión...</div>
            </div>
        );
    }

    return (
        <div className={`page-container ${styles.container}`}>
            <h2 className={styles.title}>Nueva Contraseña</h2>
            <p className={styles.description}>Establece una nueva contraseña para la cuenta <strong>{email}</strong></p>

            {error && <div className={styles.errorMsg}>{error}</div>}

            <form onSubmit={handleSubmit} className={styles.form}>
                <div className={styles.fieldGroup}>
                    <label>Nueva Contraseña</label>
                    <input
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                        minLength={8}
                        className={styles.input}
                        placeholder="••••••••"
                    />
                </div>
                <div className={styles.fieldGroup}>
                    <label>Confirmar Contraseña</label>
                    <input
                        type="password"
                        value={passwordConfirmation}
                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                        required
                        className={styles.input}
                        placeholder="••••••••"
                    />
                </div>

                <button type="submit" disabled={loading} className={styles.submitBtn}>
                    {loading ? 'Restableciendo...' : 'Guardar contraseña'}
                </button>
            </form>
        </div>
    );
}
