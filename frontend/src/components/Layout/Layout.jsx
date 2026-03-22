import React from 'react';

export const Layout = ({ children }) => {
  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <main style={{ flex: 1 }}>
        {children}
      </main>
      <footer style={{ 
        backgroundColor: '#001f3f', 
        color: '#fff', 
        padding: '3rem 5%', 
        marginTop: 'auto',
        textAlign: 'center'
      }}>
        <div style={{ marginBottom: '2rem' }}>
          <h3 style={{ fontSize: '1.5rem', fontWeight: 800 }}>GALOTXAS</h3>
          <p style={{ opacity: 0.7 }}>Federación de Galotxas - Monóvar y comarca</p>
        </div>
        <div style={{ borderTop: '1px solid rgba(255,255,255,0.1)', paddingTop: '2rem', fontSize: '0.8rem', opacity: 0.5 }}>
          © 2026 Galotxas. Todos los derechos reservados.
        </div>
      </footer>
    </div>
  );
};
