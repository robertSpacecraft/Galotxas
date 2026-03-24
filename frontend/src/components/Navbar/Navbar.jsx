import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import styles from './Navbar.module.css';
import logo from '../../assets/images/Logo_Galotxas_Femenino.png';

export const Navbar = () => {
  const { user, isAuthenticated, logout } = useAuth();

  return (
    <nav className={styles.navbar}>
      <Link to="/" className={styles.logoContainer}>
        <img src={logo} alt="Galotxas Logo" className={styles.logoImage} />
      </Link>
      <ul className={styles.navLinks}>
        <li><Link to="/" className={styles.navItem}>Inicio</Link></li>
        <li><Link to="/torneos" className={styles.navItem}>Torneos</Link></li>
        <li><Link to="/rankings" className={styles.navItem}>Rankings</Link></li>
        <li><Link to="/prensa" className={styles.navItem}>Prensa & Media</Link></li>
        <li><Link to="/nosotros" className={styles.navItem}>Nosotros</Link></li>
        <li><Link to="/federaciones" className={styles.navItem}>Federaciones</Link></li>
        <li><Link to="/documentos" className={styles.navItem}>Documentos</Link></li>
        <li><Link to="/academy" className={styles.navItem}>Academy</Link></li>
      </ul>

      <div className={styles.authSection}>
        {isAuthenticated ? (
          <div className={styles.userGreeting}>
            <span className={styles.welcomeText}>
              Hola, <span className={styles.userName}>{user?.name}</span>!
            </span>
            <Link to="/player" className={styles.miPanelLink}>Mi Panel</Link>
            <button onClick={logout} className={styles.logoutBtn}>Salir</button>
          </div>
        ) : (
          <Link to="/login" className={styles.playerAreaBtn}>Área de jugadores</Link>
        )}
      </div>
    </nav>
  );
};
