import React from 'react';
import styles from './Layout.module.css';

export const Layout = ({ children }) => {
  return (
    <div className={styles.layoutWrapper}>
      <div className={styles.main}>
        {children}
      </div>
      <footer className={styles.footer}>
        <div className={styles.footerBrand}>
          <h3 className={styles.footerTitle}>GALOTXAS</h3>
          <p className={styles.footerSubtitle}>Federación de Galotxas - Monóvar y comarca</p>
        </div>
        <div className={styles.footerBottom}>
          © 2026 Galotxas. Todos los derechos reservados.
        </div>
      </footer>
    </div>
  );
};
