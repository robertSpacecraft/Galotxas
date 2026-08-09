import { useEffect, useRef, useState } from 'react';
import { PageMetadata } from '../../components/PublicLanding/PageMetadata';
import { publicIdentityService } from './publicIdentityService';
import styles from './PublicIdentityConfirmationPage.module.css';

const modeLabels = {
  alias: 'Alias deportivo',
  name_initial: 'Nombres de pila e inicial del primer apellido',
  anonymous: 'Identidad anónima',
};

const captureTokenFromLocation = () => {
  const token = new URLSearchParams(window.location.hash.slice(1)).get('token') ?? '';
  if (window.location.hash) {
    window.history.replaceState(
      window.history.state,
      '',
      `${window.location.pathname}${window.location.search}`,
    );
  }

  return token;
};

export const PublicIdentityConfirmationPage = () => {
  const [initialToken] = useState(captureTokenFromLocation);
  const token = useRef(initialToken);
  const decisionPending = useRef(false);
  const result = useRef(null);
  const [state, setState] = useState(() => ({
    status: initialToken.length < 40 ? 'invalid' : 'loading',
    data: null,
  }));

  useEffect(() => {
    const controller = new AbortController();
    if (token.current.length >= 40) {
      publicIdentityService.lookup(token.current, { signal: controller.signal })
        .then((data) => {
          setState({ status: data ? 'ready' : 'invalid', data });
        })
        .catch((error) => {
          if (error.name === 'CanceledError') return;
          setState({
            status: error.response?.status === 429 ? 'rate-limited' : 'invalid',
            data: null,
          });
        });
    }

    return () => {
      controller.abort();
    };
  }, []);

  useEffect(() => {
    if (['confirmed', 'denied', 'invalid', 'rate-limited'].includes(state.status)) {
      result.current?.focus();
    }
  }, [state.status]);

  const decide = async (decision) => {
    if (state.status !== 'ready' || decisionPending.current) return;
    decisionPending.current = true;
    setState((current) => ({ ...current, status: 'submitting' }));
    try {
      await publicIdentityService[decision](token.current);
      token.current = '';
      setState({
        status: decision === 'confirm' ? 'confirmed' : 'denied',
        data: null,
      });
    } catch (error) {
      decisionPending.current = false;
      setState({
        status: error.response?.status === 429 ? 'rate-limited' : 'invalid',
        data: null,
      });
    }
  };

  return (
    <div className={styles.page}>
      <PageMetadata
        title="Decisión de identidad pública"
        description="Ruta temporal para registrar una decisión de identidad pública."
      />
      <section className={styles.card} aria-labelledby="identity-confirmation-title">
        <p className={styles.brand}>Club Galotxes Monòver</p>
        <h1 id="identity-confirmation-title">Identidad pública en competición</h1>

        {state.status === 'loading' ? (
          <p role="status" aria-live="polite">Comprobando el enlace…</p>
        ) : null}

        {state.status === 'ready' || state.status === 'submitting' ? (
          <>
            <p>
              Se ha solicitado el modo <strong>{modeLabels[state.data.mode]}</strong> para
              calendarios, partidos, resultados, clasificaciones, rankings e histórico de
              competición.
            </p>
            <p>
              Aviso versión {state.data.notice_version}. La inscripción y la participación
              no dependen de esta decisión. Confirmar no publica automáticamente la identidad:
              el club debe revisarla y vincularla al jugador correcto.
            </p>
            <div className={styles.actions}>
              <button
                type="button"
                className={styles.primaryButton}
                disabled={state.status === 'submitting'}
                onClick={() => decide('confirm')}
              >
                Confirmar autorización
              </button>
              <button
                type="button"
                className={styles.secondaryButton}
                disabled={state.status === 'submitting'}
                onClick={() => decide('deny')}
              >
                Rechazar y mantener “Participante”
              </button>
            </div>
            {state.status === 'submitting' ? (
              <p role="status" aria-live="polite">Registrando la decisión…</p>
            ) : null}
          </>
        ) : null}

        {state.status === 'confirmed' ? (
          <div ref={result} role="status" tabIndex="-1">
            <h2>Confirmación registrada</h2>
            <p>La identidad seguirá como “Participante” hasta que el club complete su revisión.</p>
          </div>
        ) : null}

        {state.status === 'denied' ? (
          <div ref={result} role="status" tabIndex="-1">
            <h2>Rechazo registrado</h2>
            <p>La identidad individual no se publicará y se mostrará “Participante”.</p>
          </div>
        ) : null}

        {state.status === 'invalid' ? (
          <div ref={result} role="alert" tabIndex="-1">
            <h2>Enlace no disponible</h2>
            <p>El enlace no es válido, ha caducado o ya fue utilizado.</p>
          </div>
        ) : null}

        {state.status === 'rate-limited' ? (
          <div ref={result} role="alert" tabIndex="-1">
            <h2>Demasiados intentos</h2>
            <p>Espera un momento antes de volver a intentarlo.</p>
          </div>
        ) : null}

        <p className={styles.privacyLink}>
          <a href="/legal/privacidad">Consultar la Política de privacidad</a>
        </p>
      </section>
    </div>
  );
};

export default PublicIdentityConfirmationPage;
