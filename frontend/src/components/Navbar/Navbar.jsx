import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useCmsNavigation } from '../../features/cmsNavigation/useCmsNavigation';
import {
  composePublicNavigation,
  getActivePublicNavigationItem,
  getPublicNavigationAriaCurrent,
  getVisiblePublicNavigation,
  matchesNavigationItem,
  navigationItemTypes,
  publicNavigation,
} from '../../navigation/publicNavigation';
import styles from './Navbar.module.css';
import logo from '../../assets/images/Logo_Galotxas_Femenino.png';

const closedNavigationState = (pathname) => ({
  pathname,
  menuOpen: false,
  disclosureId: null,
});

export const Navbar = () => {
  const { user, isAuthenticated, logout } = useAuth();
  const location = useLocation();
  const headerRef = useRef(null);
  const menuToggleRef = useRef(null);
  const disclosureTriggerRefs = useRef({});
  const cmsNavigation = useCmsNavigation();
  const composedNavigation = useMemo(
    () => composePublicNavigation(publicNavigation, cmsNavigation),
    [cmsNavigation],
  );
  const [navigationState, setNavigationState] = useState(
    closedNavigationState(location.pathname),
  );
  const currentState = navigationState.pathname === location.pathname
    ? navigationState
    : closedNavigationState(location.pathname);
  const { menuOpen: isMenuOpen, disclosureId: openDisclosureId } = currentState;
  const activeItem = getActivePublicNavigationItem(location.pathname, composedNavigation);
  const visibleNavigation = getVisiblePublicNavigation(composedNavigation);

  const closeNavigation = () => setNavigationState(closedNavigationState(location.pathname));

  useEffect(() => {
    let active = true;

    queueMicrotask(() => {
      if (!active) return;

      setNavigationState((state) => (
        state.pathname === location.pathname
          ? state
          : closedNavigationState(location.pathname)
      ));
    });

    return () => {
      active = false;
    };
  }, [location.pathname]);

  useEffect(() => {
    if (!isMenuOpen && !openDisclosureId) return undefined;

    const handlePointerDown = (event) => {
      if (!headerRef.current?.contains(event.target)) {
        setNavigationState(closedNavigationState(location.pathname));
      }
    };

    const handleEscape = (event) => {
      if (event.key !== 'Escape') return;

      if (openDisclosureId) {
        setNavigationState({
          pathname: location.pathname,
          menuOpen: isMenuOpen,
          disclosureId: null,
        });
        disclosureTriggerRefs.current[openDisclosureId]?.focus();
        return;
      }

      if (isMenuOpen) {
        setNavigationState(closedNavigationState(location.pathname));
        menuToggleRef.current?.focus();
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleEscape);

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isMenuOpen, location.pathname, openDisclosureId]);

  const toggleMenu = () => setNavigationState({
    pathname: location.pathname,
    menuOpen: !isMenuOpen,
    disclosureId: null,
  });

  const toggleDisclosure = (itemId) => setNavigationState({
    pathname: location.pathname,
    menuOpen: isMenuOpen,
    disclosureId: openDisclosureId === itemId ? null : itemId,
  });

  const handleLogout = () => {
    closeNavigation();
    logout();
  };

  return (
    <header ref={headerRef} className={styles.header}>
      <a href="#main-content" className={styles.skipLink}>Saltar al contenido principal</a>

      <nav className={styles.navbar} aria-label="Navegación principal">
        <Link to="/" className={styles.logoContainer} onClick={closeNavigation}>
          <img src={logo} alt="Galotxas" className={styles.logoImage} />
        </Link>

        <button
          type="button"
          ref={menuToggleRef}
          className={styles.menuToggle}
          aria-label={isMenuOpen ? 'Cerrar menú de navegación' : 'Abrir menú de navegación'}
          aria-expanded={isMenuOpen}
          aria-controls="public-navigation"
          onClick={toggleMenu}
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
          {visibleNavigation.map((item) => {
            const isActive = activeItem?.id === item.id;

            if (item.type === navigationItemTypes.link) {
              return (
                <li key={item.id} className={styles.navigationItem}>
                  <Link
                    to={item.to}
                    className={`${styles.navItem} ${isActive ? styles.navItemActive : ''}`}
                    aria-current={getPublicNavigationAriaCurrent(item, location.pathname)}
                    onClick={closeNavigation}
                  >
                    {item.label}
                  </Link>
                </li>
              );
            }

            const isOpen = openDisclosureId === item.id;

            return (
              <li key={item.id} className={styles.navigationItem}>
                <button
                  type="button"
                  ref={(element) => {
                    disclosureTriggerRefs.current[item.id] = element;
                  }}
                  className={`${styles.navItem} ${styles.disclosureTrigger} ${isActive ? styles.navItemActive : ''}`}
                  aria-expanded={isOpen}
                  aria-controls={item.panelId}
                  onClick={() => toggleDisclosure(item.id)}
                >
                  <span>{item.label}</span>
                  <span className={styles.disclosureIcon} aria-hidden="true" />
                </button>

                <ul
                  id={item.panelId}
                  className={styles.disclosurePanel}
                  hidden={!isOpen}
                >
                  {item.children.filter((child) => (
                    child.visible && child.audience === item.audience
                  )).map((child) => (
                    <li key={child.id}>
                      <Link
                        to={child.to}
                        className={`${styles.disclosureLink} ${
                          matchesNavigationItem(child, location.pathname)
                            ? styles.disclosureLinkActive
                            : ''
                        }`}
                        aria-current={getPublicNavigationAriaCurrent(child, location.pathname)}
                        onClick={closeNavigation}
                      >
                        {child.label}
                      </Link>
                    </li>
                  ))}
                </ul>
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
              <Link to="/player" className={styles.miPanelLink} onClick={closeNavigation}>Mi Panel</Link>
              <button type="button" onClick={handleLogout} className={styles.logoutBtn}>Salir</button>
            </div>
          ) : (
            <Link to="/login" className={styles.playerAreaBtn} onClick={closeNavigation}>Iniciar sesión</Link>
          )}
        </div>
      </nav>
    </header>
  );
};
