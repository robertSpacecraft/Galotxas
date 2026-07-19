import { useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import {
  getActivePublicNavigationItem,
  getPublicNavigationAriaCurrent,
  publicNavigation,
} from '../../navigation/publicNavigation';
import styles from './Navbar.module.css';
import logo from '../../assets/images/Logo_Galotxas_Femenino.png';

export const Navbar = () => {
  const { user, isAuthenticated, logout } = useAuth();
  const location = useLocation();
  const menuToggleRef = useRef(null);
  const [menuState, setMenuState] = useState({ open: false, pathname: location.pathname });
  const isMenuOpen = menuState.open && menuState.pathname === location.pathname;
  const activeItem = getActivePublicNavigationItem(location.pathname);
  const closeMenu = () => setMenuState({ open: false, pathname: location.pathname });

  useEffect(() => {
    if (!isMenuOpen) return undefined;

    const handleEscape = (event) => {
      if (event.key === 'Escape') {
        setMenuState({ open: false, pathname: location.pathname });
        menuToggleRef.current?.focus();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isMenuOpen, location.pathname]);

  return (
    <nav className={styles.navbar} aria-label="Navegación principal">
      <Link to="/" className={styles.logoContainer} onClick={closeMenu}>
        <img src={logo} alt="Galotxas" className={styles.logoImage} />
      </Link>

      <button
        type="button"
        ref={menuToggleRef}
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
        aria-label="Navegación editorial"
        className={`${styles.navLinks} ${isMenuOpen ? styles.navLinksOpen : ''}`}
      >
        {publicNavigation.map((item) => {
          const isActive = activeItem?.id === item.id;

          return (
            <li key={item.id}>
              <Link
                to={item.to}
                className={`${styles.navItem} ${isActive ? styles.navItemActive : ''}`}
                aria-current={getPublicNavigationAriaCurrent(item, location.pathname)}
                onClick={closeMenu}
              >
                {item.label}
              </Link>
            </li>
          );
        })}
      </ul>

      <div className={styles.authSection} role="group" aria-label="Cuenta">
        {isAuthenticated ? (
          <div className={styles.userGreeting}>
            <span className={styles.welcomeText}>
              Hola, <span className={styles.userName} title={user?.name}>{user?.name}</span>!
            </span>
            <Link to="/player" className={styles.miPanelLink} onClick={closeMenu}>Mi Panel</Link>
            <button type="button" onClick={logout} className={styles.logoutBtn}>Salir</button>
          </div>
        ) : (
          <Link to="/login" className={styles.playerAreaBtn} onClick={closeMenu}>Iniciar sesión</Link>
        )}
      </div>
    </nav>
  );
};
