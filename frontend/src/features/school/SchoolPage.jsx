import { LandingActions } from '../../components/PublicLanding/LandingActions';
import { LandingHeader } from '../../components/PublicLanding/LandingHeader';
import { LandingSection } from '../../components/PublicLanding/LandingSection';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { PublicLanding } from '../../components/PublicLanding/PublicLanding';
import { manualPath } from '../knowledge/knowledgeRoutes';
import { SchoolEnrollmentForm } from './SchoolEnrollmentForm';
import { SchoolLevels } from './SchoolLevels';
import { SchoolLocation } from './SchoolLocation';
import { useSchoolOverview } from './useSchoolOverview';
import styles from './SchoolPage.module.css';

const manualActions = [
  { to: manualPath(), label: 'Consultar el Manual', variant: 'secondary' },
];

const SchoolContact = ({ contact }) => {
  if (!contact.phone && !contact.email) {
    return null;
  }

  return (
    <LandingSection
      id="school-contact"
      title="Contacto"
      introduction="Utiliza los datos públicos del programa para solicitar información."
    >
      <ul className={styles.contactList}>
        {contact.phone ? (
          <li><a href={`tel:${contact.phone}`}>{contact.phone}</a></li>
        ) : null}
        {contact.email ? (
          <li><a href={`mailto:${contact.email}`}>{contact.email}</a></li>
        ) : null}
      </ul>
    </LandingSection>
  );
};

const SchoolContent = ({ school, reload }) => (
  <div className={styles.sections}>
    <LandingSection
      id="school-program"
      title="Programa"
      introduction="Consulta la información operativa disponible actualmente."
    >
      {school.name ? <h3 className={styles.programName}>{school.name}</h3> : null}
      <p
        className={`${styles.enrollmentStatus} ${
          school.enrollments_open ? styles.enrollmentOpen : styles.enrollmentClosed
        }`}
        role="status"
      >
        {school.enrollments_open
          ? 'Inscripciones abiertas.'
          : 'No se admiten solicitudes de inscripción en este momento.'}
      </p>
      {school.default_location ? (
        <div className={styles.defaultLocation}>
          <h3>Ubicación habitual</h3>
          <SchoolLocation location={school.default_location} />
        </div>
      ) : null}
    </LandingSection>

    <LandingSection
      id="school-levels"
      title="Niveles y horarios"
      introduction="Los niveles, horarios y ubicaciones se muestran en el orden publicado por la Escuela."
    >
      <SchoolLevels levels={school.levels} />
    </LandingSection>

    <SchoolContact contact={school.contact} />

    {school.enrollments_open ? (
      <LandingSection
        id="school-enrollment"
        title="Solicitud de inscripción"
        introduction="Envía una solicitud sin necesidad de crear una cuenta. Su recepción no implica que haya sido aceptada."
      >
        <SchoolEnrollmentForm
          levels={school.levels}
          reloadOverview={reload}
          identityAuthorization={school.public_identity_authorization}
        />
      </LandingSection>
    ) : null}
  </div>
);

export const SchoolPage = () => {
  const {
    data,
    status,
    error,
    reload,
  } = useSchoolOverview();

  return (
    <PublicLanding>
      <PageMetadata
        title="Escuela de Galotxas | Galotxas"
        description="Consulta niveles, horarios, ubicaciones e inscripciones de la Escuela de Galotxas."
      />
      <LandingHeader
        id="school"
        title="Escuela de Galotxas"
        introduction="Consulta la información pública del programa y su disponibilidad para nuevas solicitudes."
        actions={<LandingActions label="Aprende a jugar" actions={manualActions} />}
      />

      {status === 'loading' ? (
        <p className={styles.remoteState} role="status" aria-live="polite">
          Cargando información de la Escuela…
        </p>
      ) : null}

      {status === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>{error}</p>
          <button type="button" className={styles.secondaryButton} onClick={reload}>
            Reintentar
          </button>
        </div>
      ) : null}

      {status === 'empty' ? (
        <div className={styles.remoteState}>
          <p>La información de la Escuela no está disponible actualmente.</p>
        </div>
      ) : null}

      {status === 'content' ? <SchoolContent school={data} reload={reload} /> : null}
    </PublicLanding>
  );
};

export default SchoolPage;
