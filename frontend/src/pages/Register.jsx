import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
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
    const navigate = useNavigate();
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
                    Object.entries(preparedPlayerData).filter(([_, v]) => v !== '' && v !== null)
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
            <h2 className={styles.title}>Registro de Usuario</h2>
            
            {error && <div className={styles.errorMsg}>{error}</div>}

            <form onSubmit={handleSubmit} className={styles.form}>
                <div className={styles.row}>
                    <div className={styles.fieldGroup}>
                        <label>Nombre *</label>
                        <input
                            type="text"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="Tu nombre"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label>Apellidos *</label>
                        <input
                            type="text"
                            name="lastname"
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
                        <label>Correo Electrónico *</label>
                        <input
                            type="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="tu@email.com"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label>Confirmar Correo *</label>
                        <div className={styles.inputWrapper}>
                            <input
                                type="email"
                                name="email_confirmation"
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
                        <label>Contraseña * (min. 8 caracteres)</label>
                        <input
                            type="password"
                            name="password"
                            value={formData.password}
                            onChange={handleChange}
                            required
                            className={styles.input}
                            placeholder="••••••••"
                        />
                    </div>
                    <div className={styles.fieldGroup}>
                        <label>Confirmar Contraseña *</label>
                        <div className={styles.inputWrapper}>
                            <input
                                type="password"
                                name="password_confirmation"
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

                <div className={styles.checkboxGroup} onClick={() => setIsPlayer(!isPlayer)}>
                    <div className={`${styles.checkbox} ${isPlayer ? styles.checked : ''}`}>
                        {isPlayer && <CheckIcon />}
                    </div>
                    <span>Soy jugador</span>
                </div>

                {isPlayer && (
                    <div className={styles.playerSection}>
                        <h3 className={styles.sectionTitle}>Perfil de Jugador</h3>
                        
                        <div className={styles.row}>
                            <div className={styles.fieldGroup}>
                                <label>Apodo (Nickname)</label>
                                <input
                                    type="text"
                                    name="nickname"
                                    value={playerData.nickname}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                    placeholder="Tu apodo en la pista"
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label>DNI / NIE {playerData.birth_date && (new Date().getFullYear() - new Date(playerData.birth_date).getFullYear() >= 18) && '*'}</label>
                                <input
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
                                <label>Fecha de Nacimiento</label>
                                <input
                                    type="date"
                                    name="birth_date"
                                    value={playerData.birth_date}
                                    onChange={handlePlayerChange}
                                    className={styles.input}
                                />
                            </div>
                            <div className={styles.fieldGroup}>
                                <label>Género</label>
                                <select 
                                    name="gender" 
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
                                <label>Nivel de juego (1-10) *</label>
                                <input
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
                                <label>Nº Licencia</label>
                                <input
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
                                <label>Mano Dominante</label>
                                <select 
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
                            <label>Notas / Observaciones</label>
                            <textarea
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
