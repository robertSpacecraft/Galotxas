import { useAuth } from '../context/AuthContext';
import styles from './Dashboard.module.css';

export default function Dashboard() {
    const { user } = useAuth();

    return (
        <div className="page-container">
            <h1>Mi panel de jugador</h1>

            <div className={styles.profileCard}>
                <div>
                    <span className={styles.label}>Nombre</span>
                    <span className={styles.value}>{user.name}</span>
                </div>

                <div>
                    <span className={styles.label}>Email</span>
                    <span className={styles.value}>{user.email}</span>
                </div>

                <div>
                    <span className={styles.label}>Perfil de jugador</span>
                    <span className={`${styles.value} ${user.player ? styles.successValue : styles.secondaryValue}`}>
                        {user.player ? user.player.name : 'No vinculado a jugador'}
                    </span>
                </div>
            </div>
        </div>
    );
}