import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import styles from './Register.module.css';

const CheckIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
);

const XIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
);

export default function Register() {
    const { register, createPlayerProfile } = useAuth();
    
    const [formData, setFormData] = useState({
        name: '',
        lastname: '',
        email: '',
        email_confirmation: '',
        password: '',
        password_confirmation: ''
    });

    const [isPlayer, setIsPlayer] = useState(false);
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

    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handlePlayerChange = (e) => {
        const { name, value } = e.target;
        setPlayerData(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);

        // Validation
        if (formData.email !== formData.email_confirmation) {
            setError('Los correos electrónicos no coinciden');
            return;
        }
        if (formData.password !== formData.password_confirmation) {
            setError('Las contraseñas no coinciden');
            return;
        }

        setLoading(true);
        try {
            // 1. Register User
            await register({
                name: formData.name,
                lastname: formData.lastname,
                email: formData.email,
                password: formData.password,
                password_confirmation: formData.password_confirmation
            });

            // 2. If "Soy jugador", Create Player Profile
            if (isPlayer) {
                // Ensure level is a number
                const preparedPlayerData = {
                    ...playerData,
                    level: playerData.level ? parseInt(playerData.level, 10) : null
                };

                // Filter empty optional fields
                const filteredPlayerProfile = Object.fromEntries(
                    Object.entries(preparedPlayerData).filter(([, value]) => value !== '' && value !== null)
                );
                
                try {
                    await createPlayerProfile(filteredPlayerProfile);
                } catch (profError) {
                    if (profError.response?.status === 409 && 
                        profError.response?.data?.message?.includes('ya tiene un perfil')) {
                        console.warn('Player profile already exists, skipping creation.');
                    } else {
                        // If it's a 409, the profile exists. If it's something else, we log it but STILL redirect 
                        // because the core user account was already created successfully.
                        console.error("Error creating player profile:", profError.response?.data || profError);
                        alert("Aviso: El usuario se ha creado, pero hubo un problema al guardar los datos de jugador: " + 
                              (profError.response?.data?.message || "Error desconocido."));
                    }

                }
            }

            console.log('Registro y perfil completados. Forzando recarga hacia /player...');
            // Utilizamos window.location para forzar la recarga del estado de la app en este caso complejo
            window.location.href = '/player';
        } catch (err) {
            console.error("Error general en el registro:", err);
            setError(err.response?.data?.message || 'Error en el registro. Revisa los datos.');
            setLoading(false);
        }
    };

    const isEmailValid = formData.email && formData.email === formData.email_confirmation;
    const isPasswordValid = formData.password && formData.password === formData.password_confirmation;

    return (
        <div className={`page-container ${styles.registerContainer}`}>
            <h1 className={styles.title}>Registro de Usuario</h1>
            
            {error && <div className={styles.errorMsg}>{error}</div>}

            <form onSubmit={handleSubmit} className={styles.form}>
                <div className={styles.row}>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-name">Nombre *</label>
                        <input
                            id="register-name"
                            type="text"
                            name="name"
                            autoComplete="given-name"
                            value={formData.name}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="Tu nombre"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-lastname">Apellidos *</label>
                        <input
                            id="register-lastname"
                            type="text"
                            name="lastname"
                            autoComplete="family-name"
                            value={formData.lastname}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="Tus apellidos"
                        />
                    </div>
                </div>

                <div className={styles.row}>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-email">Correo Electrónico *</label>
                        <input
                            id="register-email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            value={formData.email}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="tu@email.com"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-email-confirmation">Confirmar Correo *</label>
                        <div className={styles.inputWrapper}>
                            <input
                                id="register-email-confirmation"
                                type="email"
                                name="email_confirmation"
                                autoComplete="email"
                                value={formData.email_confirmation}
                                onChange={handleChange}
                                required
                                className={styles.input}
                                placeholder="Repite tu@email.com"
                            />
                            <div className={styles.validationIcon}>
                                {formData.email_confirmation && (
                                    isEmailValid ? <span className={styles.success}><CheckIcon /></span> : <span className={styles.error}><XIcon /></span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className={styles.row}>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-password">Contraseña * (min. 8 caracteres)</label>
                        <input
                            id="register-password"
                            type="password"
                            name="password"
                            autoComplete="new-password"
                            value={formData.password}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="••••••••"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label htmlFor="register-password-confirmation">Confirmar Contraseña *</label>
                        <div className={styles.inputWrapper}>
                            <input
                                id="register-password-confirmation"
                                type="password"
                                name="password_confirmation"
                                autoComplete="new-password"
                                value={formData.password_confirmation}
                                onChange={handleChange}
                                required
                                className={styles.input}
                                placeholder="Repite contraseña"
                            />
                            <div className={styles.validationIcon}>
                                {formData.password_confirmation && (
                                    isPasswordValid ? <span className={styles.success}><CheckIcon /></span> : <span className={styles.error}><XIcon /></span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <label className={styles.checkboxGroup} htmlFor="register-is-player">
                    <input
                        id="register-is-player"
                        type="checkbox"
                        checked={isPlayer}
                        onChange={(event) => setIsPlayer(event.target.checked)}
                        className={styles.checkboxInput}
                    />
                    <span className={`${styles.checkbox} ${isPlayer ? styles.checked : ''}`} aria-hidden="true">
                        {isPlayer && <CheckIcon />}
                    </span>
                    <span>Soy jugador</span>
                </label>

                {isPlayer && (
                    <div className={styles.playerSection}>
                        <h3 className={styles.sectionTitle}>Perfil de Jugador</h3>
                        
                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label htmlFor="player-nickname">Apodo (Nickname)</label>
                                <input
                                    id="player-nickname"
                                    type="text"
                                    name="nickname"
                                    value={playerData.nickname}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                    placeholder="Tu apodo en la pista"
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label htmlFor="player-dni">DNI / NIE {playerData.birth_date && (new Date().getFullYear() - new Date(playerData.birth_date).getFullYear() >= 18) && '*'}</label>
                                <input
                                    id="player-dni"
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
                                <label htmlFor="player-birth-date">Fecha de Nacimiento</label>
                                <input
                                    id="player-birth-date"
                                    type="date"
                                    name="birth_date"
                                    autoComplete="bday"
                                    value={playerData.birth_date}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label htmlFor="player-gender">Género</label>
                                <select 
                                    id="player-gender"
                                    name="gender" 
                                    autoComplete="sex"
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
                                <label htmlFor="player-level">Nivel de juego (1-10) *</label>
                                <input
                                    id="player-level"
                                    type="number"
                                    name="level"
                                    min="1"
                                    max="10"
                                    value={playerData.level}
                                    onChange={handlePlayerChange}
                                    required={isPlayer}
                                    className={styles.input}
                                    placeholder="Tu nivel"
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label htmlFor="player-license-number">Nº Licencia</label>
                                <input
                                    id="player-license-number"
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
                                <label htmlFor="player-dominant-hand">Mano Dominante</label>
                                <select 
                                    id="player-dominant-hand"
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
                            <label htmlFor="player-notes">Notas / Observaciones</label>
                            <textarea
                                id="player-notes"
                                name="notes"
                                value={playerData.notes}
                                onChange={handlePlayerChange}
                                className={styles.textarea}
                                placeholder="Algo que debamos saber..."
                            />
                        </div>
                    </div>
                )}

                <button 
                    type="submit" 
                    disabled={loading || !isEmailValid || !isPasswordValid} 
                    className={styles.submitBtn}
                >
                    {loading ? 'Registrando...' : 'Registrarse'}
                </button>
            </form>

            <div className={styles.loginLink}>
                ¿Ya tienes cuenta? <Link to="/login" className={styles.link}>Inicia sesión</Link>
            </div>
        </div>
    );
}
