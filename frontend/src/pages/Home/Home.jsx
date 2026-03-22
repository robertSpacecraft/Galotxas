import React from 'react';
import { Layout } from '../../components/Layout/Layout';
import { Hero } from '../../components/Hero/Hero';

export const Home = () => {
  return (
    <Layout>
      <Hero />
      <section style={{ padding: '5rem 10%', backgroundColor: '#f9f9f9', textAlign: 'center' }}>
        <h2 style={{ fontSize: '2.5rem', fontWeight: 800, color: '#003366', marginBottom: '1.5rem' }}>Bienvenidos a la Plataforma Oficial</h2>
        <p style={{ fontSize: '1.1rem', color: '#666', lineHeight: '1.6', maxWidth: '800px', margin: '0 auto' }}>
          Explora los últimos torneos, consulta rankings históricos y mantente al día con toda la prensa y media del mundo de las Galotxas.
        </p>
      </section>
      
      {/* Dynamic/Static Content Grid Placeholder inspired by padelfip */}
      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', 
        gap: '2rem', 
        padding: '0 10% 5rem',
        backgroundColor: '#f9f9f9'
      }}>
        <div style={cardStyle}>
          <h3 style={cardTitle}>Prensa & Media</h3>
          <p style={cardText}>Últimas noticias y reportajes sobre las partidas más emocionantes.</p>
        </div>
        <div style={cardStyle}>
          <h3 style={cardTitle}>Federaciones</h3>
          <p style={cardText}>Consulta los clubes y federaciones inscritas en la plataforma.</p>
        </div>
        <div style={cardStyle}>
          <h3 style={cardTitle}>Academy</h3>
          <p style={cardText}>Aprende las técnicas y reglas oficiales de Galotxas con nuestros expertos.</p>
        </div>
      </div>
    </Layout>
  );
};

const cardStyle = {
  background: '#fff',
  padding: '2.5rem',
  borderRadius: '12px',
  boxShadow: '0 4px 15px rgba(0,0,0,0.05)',
  textAlign: 'left',
  border: '1px solid #eee'
};

const cardTitle = {
  fontSize: '1.5rem',
  fontWeight: 700,
  color: '#003366',
  marginBottom: '1rem'
};

const cardText = {
  color: '#666',
  lineHeight: '1.5'
};
