import { LandingActions } from '../../components/PublicLanding/LandingActions';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { seoRouteClassifications } from '../../seo/seoManifest';
import styles from './NotFoundPage.module.css';

const recoveryActions = [
  { to: '/', label: 'Volver a Inicio', variant: 'primary' },
  { to: '/competicion', label: 'Ir a Competición', variant: 'secondary' },
];

export const NotFoundPage = () => (
  <div className={styles.container}>
    <PageMetadata
      title="Página no encontrada"
      description="La página solicitada no está disponible en Galotxas."
      classification={seoRouteClassifications.notFound}
      canonicalPath={null}
    />
    <div className={styles.content}>
      <h1 className={styles.title}>Página no encontrada</h1>
      <p className={styles.description}>La página que buscas no está disponible.</p>
      <div className={styles.recovery}>
        <LandingActions label="Opciones de recuperación" actions={recoveryActions} />
      </div>
    </div>
  </div>
);
