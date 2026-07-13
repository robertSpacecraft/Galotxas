import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import styles from './Navbar.module.css';
import logo from '../../assets/images/Logo_Galotxas_Femenino.png';

export const Navbar = () => {
  const { user, isAuthenticated, logout } = useAuth();
  const location = useLocation();
  const [menuState, setMenuState] = useState({ open: false, pathname: location.pathname });
  const isMenuOpen = menuState.open && menuState.pathname === location.pathname;
  const closeMenu = () => setMenuState({ open: false, pathname: location.pathname });

  useEffect(() => {
    if (!isMenuOpen) return undefined;

    const handleEscape = (event) => {
      if (event.key === 'Escape') {
        setMenuState({ open: false, pathname: location.pathname });
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isMenuOpen, location.pathname]);

  return (
    <nav className={styles.navbar} aria-label="Navegación principal">
      <Link to="/" className={styles.logoContainer} onClick={closeMenu}>
        <img src={logo} alt="Galotxas Logo" className={styles.logoImage} />
      </Link>

      <button
        type="button"
        className={styles.menuToggle}
        aria-label={isMenuOpen ? 'Cerrar menú de navegación' : 'Abrir menú de navegación'}
        aria-expanded={isMenuOpen}
        aria-controls="public-navigation"
        onClick={() => setMenuState({ open: !isMenuOpen, pathname: location.pathname })}
      >
        <span className={styles.menuIcon} aria-hidden="true">
          <span />
          <span />
          <span />
        </span>
        <span>Menú</span>
      </button>

      <ul
        id="public-navigation"
        className={`${styles.navLinks} ${isMenuOpen ? styles.navLinksOpen : ''}`}
      >
        <li><Link to="/" className={styles.navItem} onClick={closeMenu}>Inicio</Link></li>
        <li><Link to="/torneos" className={styles.navItem} onClick={closeMenu}>Torneos</Link></li>
        <li><Link to="/rankings" className={styles.navItem} onClick={closeMenu}>Rankings</Link></li>
        <li><Link to="/contenidos/prensa-media" className={styles.navItem} onClick={closeMenu}>Prensa & Media</Link></li>
        <li><Link to="/contenidos/nosotros" className={styles.navItem} onClick={closeMenu}>Nosotros</Link></li>
        <li><Link to="/contenidos/federaciones" className={styles.navItem} onClick={closeMenu}>Federaciones</Link></li>
        <li><Link to="/contenidos" className={styles.navItem} onClick={closeMenu}>Contenidos</Link></li>
        <li><Link to="/contenidos/academy" className={styles.navItem} onClick={closeMenu}>Academy</Link></li>
      </ul>

      <div className={styles.authSection}>
        {isAuthenticated ? (
          <div className={styles.userGreeting}>
            <span className={styles.welcomeText}>
              Hola, <span className={styles.userName} title={user?.name}>{user?.name}</span>!
            </span>
            <Link to="/player" className={styles.miPanelLink} onClick={closeMenu}>Mi Panel</Link>
            <button onClick={logout} className={styles.logoutBtn}>Salir</button>
          </div>
        ) : (
          <Link to="/login" className={styles.playerAreaBtn} onClick={closeMenu}>Área de jugadores</Link>
        )}
      </div>
    </nav>
  );
};
