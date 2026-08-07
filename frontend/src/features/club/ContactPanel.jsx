import { useCallback, useEffect, useRef, useState } from 'react';
import { contactService } from '../contact/contactService';
import { contactFormNotice } from '../legal/formNoticeRepository';
import { ContactForm } from './ContactForm';
import styles from './ClubPage.module.css';

const initialState = {
  status: 'loading',
  enabled: false,
};

const hasCurrentNotice = (config) => (
  config.enabled
  && contactFormNotice
  && config.notice_id === contactFormNotice.id
  && config.notice_version === contactFormNotice.version
  && config.privacy_url === contactFormNotice.privacyUrl
);

export const ContactPanel = () => {
  const [state, setState] = useState(initialState);
  const activeRequest = useRef(0);

  const load = useCallback(async () => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;
    setState(initialState);

    try {
      const config = await contactService.getConfig();

      if (activeRequest.current === requestId) {
        setState({
          status: hasCurrentNotice(config) ? 'enabled' : 'disabled',
          enabled: hasCurrentNotice(config),
        });
      }
    } catch {
      if (activeRequest.current === requestId) {
        setState({ status: 'error', enabled: false });
      }
    }
  }, []);

  useEffect(() => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;

    contactService.getConfig().then(
      (config) => {
        if (activeRequest.current === requestId) {
          setState({
            status: hasCurrentNotice(config) ? 'enabled' : 'disabled',
            enabled: hasCurrentNotice(config),
          });
        }
      },
      () => {
        if (activeRequest.current === requestId) {
          setState({ status: 'error', enabled: false });
        }
      },
    );

    return () => {
      activeRequest.current += 1;
    };
  }, []);

  return (
    <section className={styles.contactPanel} aria-labelledby="club-contact-form-title">
      <h2 id="club-contact-form-title">Formulario de contacto</h2>

      {state.status === 'loading' ? (
        <p className={styles.remoteState} role="status" aria-live="polite">
          Comprobando la disponibilidad del formulario…
        </p>
      ) : null}

      {state.status === 'disabled' ? (
        <p className={styles.remoteState}>
          El formulario no está disponible actualmente. Puedes utilizar los canales publicados
          en esta página.
        </p>
      ) : null}

      {state.status === 'error' ? (
        <div className={styles.errorState} role="alert">
          <p>No se ha podido comprobar la disponibilidad del formulario.</p>
          <button type="button" className={styles.secondaryButton} onClick={load}>
            Reintentar
          </button>
        </div>
      ) : null}

      {state.status === 'enabled' && state.enabled ? (
        <ContactForm notice={contactFormNotice} />
      ) : null}
    </section>
  );
};
