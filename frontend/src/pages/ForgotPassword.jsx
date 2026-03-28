import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import styles from './AuthForms.module.css';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);
    const { forgotPassword } = useAuth();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);
        setMessage(null);

        try {
            await forgotPassword(email);
        } catch (err) {
            // Enmascarar errores 4xx (como 404, 422) por seguridad UX
            if (!err.response || err.response.status >= 500) {
                setError('Error técnico al conectar con el servidor. Intenta más tarde.');
                setLoading(false);
                return;
            }
        }
        
        // Siempre mostrar este mensaje neutro si no hay error técnico grave
        setMessage('Si el correo existe, se ha enviado un enlace para restablecer la contraseña.');
        setLoading(false);

    };

    if (message) {
        return (
            <div className={`page-container ${styles.container}`}>
                <h2 className={styles.title}>Revisa tu correo</h2>
                <div className={styles.successMsg}>{message}</div>
                <div className={styles.backLink}>
                    <Link to="/login" className={styles.link}>Volver al inicio de sesión</Link>
                </div>
            </div>
        );
    }

    return (
        <div className={`page-container ${styles.container}`}>
            <h2 className={styles.title}>Recuperar Contraseña</h2>
            <p className={styles.description}>
                Introduce tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
            </p>

            {error && <div className={styles.errorMsg}>{error}</div>}

            <form onSubmit={handleSubmit} className={styles.form}>
                <div className={styles.fieldGroup}>
                    <label>Correo Electrónico</label>
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        className={styles.input}
                        placeholder="tu@email.com"
                    />
                </div>

                <button type="submit" disabled={loading || !email} className={styles.submitBtn}>
                    {loading ? 'Enviando...' : 'Enviar enlace'}
                </button>
            </form>

            <div className={styles.backLink}>
                <Link to="/login" className={styles.link}>Volver al inicio de sesión</Link>
            </div>
        </div>
    );
}
