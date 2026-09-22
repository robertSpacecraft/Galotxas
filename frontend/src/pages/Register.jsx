import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { accountProfileNotice } from '../features/legal/formNoticeRepository';
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
    const [generalDeclarationAccepted, setGeneralDeclarationAccepted] = useState(false);
    const [birthDateConfirmed, setBirthDateConfirmed] = useState(false);

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
        if (isPlayer && !playerData.birth_date) {
            setError('La fecha de nacimiento es obligatoria para crear el perfil de jugador.');
            return;
        }
        if (isPlayer && !birthDateConfirmed) {
            setError('Debes confirmar que la fecha de nacimiento indicada es correcta.');
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
                password_confirmation: formData.password_confirmation,
                profile_declaration_accepted: generalDeclarationAccepted,
                profile_notice_id: accountProfileNotice?.id,
                profile_notice_version: accountProfileNotice?.version
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

                filteredPlayerProfile.birth_date_confirmed = birthDateConfirmed;
                filteredPlayerProfile.profile_notice_id = accountProfileNotice?.id;
                filteredPlayerProfile.profile_notice_version = accountProfileNotice?.version;
                
                try {
                    await createPlayerProfile(filteredPlayerProfile);
                } catch (profError) {
                    if (profError.response?.status === 409 && 
                        profError.response?.data?.message?.includes('ya tiene un perfil')) {
                        console.warn('Player profile already exists, skipping creation.');
                    } else {
                        // If it's a 409, the profile exists. If it's something else, we log it but STILL redirect 
                        // because the core user account was already created successfully.
                        console.error('No se ha podido crear el perfil de jugador tras el registro.');
                        alert("Aviso: El usuario se ha creado, pero hubo un problema al guardar los datos de jugador: " + 
                              (profError.response?.data?.message || "Error desconocido."));
                    }

                }
            }

            // Utilizamos window.location para forzar la recarga del estado de la app en este caso complejo
            window.location.href = '/player';
        } catch (err) {
            console.error('No se ha podido completar el registro.');
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

                <label className={styles.declarationGroup} htmlFor="register-profile-declaration">
                    <input
                        id="register-profile-declaration"
                        type="checkbox"
                        checked={generalDeclarationAccepted}
                        onChange={(event) => setGeneralDeclarationAccepted(event.target.checked)}
                        required
                    />
                    <span>
                        He leído la <Link to="/legal/privacidad" className={styles.link}>Política de Privacidad</Link> y declaro que los datos facilitados son exactos y veraces.
                    </span>
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
                                    aria-describedby="register-nickname-help"
                                />
                                <small id="register-nickname-help">Apodo deportivo por el que te conocen en la pista. No uses tu nombre habitual salvo que también sea tu apodo deportivo.</small>
                            </div>
                            <div className={styles.fieldGroup}>
                                <label htmlFor="player-dni">DNI / NIE</label>
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
                                <label htmlFor="player-birth-date">Fecha de Nacimiento *</label>
                                <input
                                    id="player-birth-date"
                                    type="date"
                                    name="birth_date"
                                    autoComplete="bday"
                                    value={playerData.birth_date}
                                    onChange={handlePlayerChange}
                                    required
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
                                <label htmlFor="player-level">Nivel de juego (1-10)</label>
                                <input
                                    id="player-level"
                                    type="number"
                                    name="level"
                                    min="1"
                                    max="10"
                                    value={playerData.level}
                                    onChange={handlePlayerChange}
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

                        {playerData.birth_date && (
                            <label className={styles.declarationGroup} htmlFor="register-birth-date-confirmed">
                                <input
                                    id="register-birth-date-confirmed"
                                    type="checkbox"
                                    checked={birthDateConfirmed}
                                    onChange={(event) => setBirthDateConfirmed(event.target.checked)}
                                    required
                                />
                                <span>Confirmo que la fecha de nacimiento indicada es correcta.</span>
                            </label>
                        )}
                    </div>
                )}

                <button 
                    type="submit" 
                    disabled={loading || !isEmailValid || !isPasswordValid || !generalDeclarationAccepted || !accountProfileNotice}
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
