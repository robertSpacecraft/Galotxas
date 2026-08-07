import { useEffect, useRef, useState } from 'react';
import { contactService } from '../contact/contactService';
import { LegalRenderer } from '../legal/LegalRenderer';
import styles from './ClubPage.module.css';

const emptyFields = {
  name: '',
  email: '',
  subject: '',
  message: '',
  privacy_accepted: false,
  website: '',
};

const fieldOrder = ['name', 'email', 'subject', 'message', 'privacy_accepted'];

const firstMessage = (value) => (
  Array.isArray(value) ? value.find((message) => typeof message === 'string') : null
);

const validateContactForm = (fields) => {
  const errors = {};
  const name = fields.name.trim();
  const email = fields.email.trim();
  const subject = fields.subject.trim();
  const message = fields.message.trim();

  if (name.length < 2) {
    errors.name = 'Indica un nombre de al menos 2 caracteres.';
  } else if (name.length > 120) {
    errors.name = 'El nombre no puede superar los 120 caracteres.';
  }

  if (!email) {
    errors.email = 'Indica un correo electrónico.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = 'Indica un correo electrónico válido.';
  } else if (email.length > 254) {
    errors.email = 'El correo no puede superar los 254 caracteres.';
  }

  if (subject.length < 3) {
    errors.subject = 'Indica un asunto de al menos 3 caracteres.';
  } else if (subject.length > 200) {
    errors.subject = 'El asunto no puede superar los 200 caracteres.';
  }

  if (message.length < 10) {
    errors.message = 'Escribe un mensaje de al menos 10 caracteres.';
  } else if (message.length > 5000) {
    errors.message = 'El mensaje no puede superar los 5000 caracteres.';
  }

  if (!fields.privacy_accepted) {
    errors.privacy_accepted = 'Debes confirmar el envío de los datos introducidos.';
  }

  return errors;
};

const FieldError = ({ field, errors }) => (
  errors[field] ? (
    <span id={`club-contact-${field}-error`} className={styles.fieldError}>
      {errors[field]}
    </span>
  ) : null
);

export const ContactForm = ({ notice }) => {
  const [fields, setFields] = useState(emptyFields);
  const [errors, setErrors] = useState({});
  const [submitState, setSubmitState] = useState('idle');
  const [generalMessage, setGeneralMessage] = useState(null);
  const fieldRefs = useRef({});
  const pendingFieldFocus = useRef(null);
  const messageRef = useRef(null);
  const successRef = useRef(null);
  const submittingRef = useRef(false);
  const isSubmitting = submitState === 'submitting';

  useEffect(() => {
    if (pendingFieldFocus.current) {
      fieldRefs.current[pendingFieldFocus.current]?.focus();
      pendingFieldFocus.current = null;
    }
  }, [errors]);

  useEffect(() => {
    if (generalMessage && submitState !== 'invalid') {
      messageRef.current?.focus();
    }
  }, [generalMessage, submitState]);

  useEffect(() => {
    if (submitState === 'success') {
      successRef.current?.focus();
    }
  }, [submitState]);

  const updateField = (event) => {
    const { checked, name, type, value } = event.target;
    setFields((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const fieldProps = (name) => ({
    id: `club-contact-${name}`,
    name,
    value: fields[name],
    onChange: updateField,
    ref: (element) => {
      fieldRefs.current[name] = element;
    },
    'aria-invalid': Boolean(errors[name]),
    'aria-describedby': errors[name] ? `club-contact-${name}-error` : undefined,
  });

  const focusFirstError = (nextErrors) => {
    pendingFieldFocus.current = fieldOrder.find((field) => nextErrors[field]) ?? null;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (isSubmitting || submittingRef.current) {
      return;
    }

    const nextErrors = validateContactForm(fields);
    setErrors(nextErrors);
    setGeneralMessage(null);

    if (Object.keys(nextErrors).length > 0) {
      setSubmitState('invalid');
      focusFirstError(nextErrors);
      return;
    }

    const payload = {
      name: fields.name.trim(),
      email: fields.email.trim(),
      subject: fields.subject.trim(),
      message: fields.message.trim(),
      privacy_accepted: fields.privacy_accepted,
      privacy_notice_id: notice.id,
      privacy_notice_version: notice.version,
      website: fields.website,
    };

    submittingRef.current = true;
    setSubmitState('submitting');

    try {
      await contactService.submit(payload);
      setFields(emptyFields);
      setErrors({});
      setGeneralMessage(null);
      setSubmitState('success');
    } catch (error) {
      submittingRef.current = false;

      if (error?.status === 422) {
        const backendErrors = Object.fromEntries(
          fieldOrder
            .map((field) => [field, firstMessage(error.errors?.[field])])
            .filter(([, message]) => message),
        );

        setErrors(backendErrors);
        setGeneralMessage(
          firstMessage(error.errors?.payload)
          ?? (Object.keys(backendErrors).length > 0
            ? 'Revisa los campos indicados.'
            : error.message),
        );
        setSubmitState('invalid');
        focusFirstError(backendErrors);
        return;
      }

      setErrors({});
      setGeneralMessage(error?.message || 'No se ha podido enviar el mensaje.');
      setSubmitState(error?.status === 429 ? 'rateLimited' : 'error');
    }
  };

  if (submitState === 'success') {
    return (
      <div ref={successRef} className={styles.successState} role="status" tabIndex="-1">
        <h3>Mensaje recibido</h3>
        <p>Tu mensaje se ha recibido correctamente.</p>
      </div>
    );
  }

  return (
    <form className={styles.form} noValidate onSubmit={handleSubmit}>
      {generalMessage ? (
        <div ref={messageRef} className={styles.formMessage} role="alert" tabIndex="-1">
          {generalMessage}
        </div>
      ) : null}

      <p className={styles.requiredHelp}>
        Los campos marcados con <span aria-hidden="true">*</span> son obligatorios.
      </p>

      <aside
        id="club-contact-privacy-notice"
        className={styles.privacyNotice}
        aria-labelledby="club-contact-privacy-notice-title"
      >
        <h3 id="club-contact-privacy-notice-title">Información sobre protección de datos</h3>
        <LegalRenderer blocks={notice.blocks} />
        <p className={styles.noticeMeta}>
          Aviso {notice.id}, versión {notice.version}. Consulta la{' '}
          <a href={notice.privacyUrl} target="_blank" rel="noopener noreferrer">
            Política de privacidad
            <span className={styles.srOnly}> (se abre en una pestaña nueva)</span>
          </a>.
        </p>
      </aside>

      <div className={styles.field}>
        <label htmlFor="club-contact-name">
          Nombre <span aria-hidden="true">*</span>
        </label>
        <input {...fieldProps('name')} type="text" autoComplete="name" maxLength="120" required />
        <FieldError field="name" errors={errors} />
      </div>

      <div className={styles.field}>
        <label htmlFor="club-contact-email">
          Correo electrónico <span aria-hidden="true">*</span>
        </label>
        <input
          {...fieldProps('email')}
          type="email"
          autoComplete="email"
          maxLength="254"
          required
        />
        <FieldError field="email" errors={errors} />
      </div>

      <div className={styles.field}>
        <label htmlFor="club-contact-subject">
          Asunto <span aria-hidden="true">*</span>
        </label>
        <input {...fieldProps('subject')} type="text" maxLength="200" required />
        <FieldError field="subject" errors={errors} />
      </div>

      <div className={styles.field}>
        <label htmlFor="club-contact-message">
          Mensaje <span aria-hidden="true">*</span>
        </label>
        <textarea {...fieldProps('message')} rows="8" maxLength="5000" required />
        <FieldError field="message" errors={errors} />
      </div>

      <div className={styles.consentField}>
        <input
          id="club-contact-privacy_accepted"
          name="privacy_accepted"
          type="checkbox"
          checked={fields.privacy_accepted}
          onChange={updateField}
          ref={(element) => {
            fieldRefs.current.privacy_accepted = element;
          }}
          aria-invalid={Boolean(errors.privacy_accepted)}
          aria-describedby={
            [
              'club-contact-privacy-notice',
              errors.privacy_accepted ? 'club-contact-privacy_accepted-error' : null,
            ].filter(Boolean).join(' ')
          }
          required
        />
        <div>
          <label htmlFor="club-contact-privacy_accepted">
            He leído la información sobre protección de datos y acepto que el club trate
            mis datos para atender esta consulta.
            {' '}<span aria-hidden="true">*</span>
          </label>
          <FieldError field="privacy_accepted" errors={errors} />
        </div>
      </div>

      <div className={styles.honeypot} aria-hidden="true">
        <label htmlFor="club-contact-website">No completes este campo</label>
        <input
          id="club-contact-website"
          name="website"
          type="text"
          value={fields.website}
          onChange={updateField}
          autoComplete="off"
          tabIndex="-1"
        />
      </div>

      <button type="submit" className={styles.primaryButton} disabled={isSubmitting}>
        {isSubmitting ? 'Enviando mensaje…' : 'Enviar mensaje'}
      </button>
      {isSubmitting ? (
        <p className={styles.srOnly} role="status" aria-live="polite">
          Enviando el mensaje.
        </p>
      ) : null}
    </form>
  );
};
