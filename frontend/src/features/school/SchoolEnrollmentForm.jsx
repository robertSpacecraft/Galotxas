import { useEffect, useMemo, useRef, useState } from 'react';
import { getParticipantAgeStatus } from './schoolDate';
import { schoolService } from './schoolService';
import {
  minorPublicIdentityNotice,
  schoolEnrollmentNotice,
} from '../legal/formNoticeRepository';
import { LegalRenderer } from '../legal/LegalRenderer';
import styles from './SchoolPage.module.css';

const emptyFields = {
  participant_name: '',
  participant_birth_date: '',
  school_level_id: '',
  contact_phone: '',
  contact_email: '',
  guardian_name: '',
  guardian_relationship: '',
  privacy_acknowledged: false,
  public_identity_mode: 'anonymous',
  guardian_authority_declared: false,
  website: '',
};

const fieldOrder = [
  'participant_name',
  'participant_birth_date',
  'school_level_id',
  'contact_phone',
  'contact_email',
  'guardian_name',
  'guardian_relationship',
  'privacy_acknowledged',
  'guardian_authority_declared',
];

const fieldLabels = {
  participant_name: 'Nombre completo del participante',
  participant_birth_date: 'Fecha de nacimiento',
  school_level_id: 'Nivel solicitado',
  contact_phone: 'Teléfono de contacto',
  contact_email: 'Correo electrónico de contacto',
  guardian_name: 'Nombre completo del representante',
  guardian_relationship: 'Relación con el participante',
  privacy_acknowledged: 'Información de privacidad de la inscripción',
  guardian_authority_declared: 'Declaración de patria potestad o tutela',
};

const firstMessage = (value) => (
  Array.isArray(value) ? value.find((message) => typeof message === 'string') : null
);

const validateSchoolEnrollment = (
  fields,
  levels,
  identityAuthorization,
  referenceDate = new Date(),
) => {
  const errors = {};
  const ageStatus = getParticipantAgeStatus(fields.participant_birth_date, referenceDate);

  if (!fields.participant_name.trim()) {
    errors.participant_name = 'Indica el nombre completo del participante.';
  }

  if (!fields.participant_birth_date) {
    errors.participant_birth_date = 'Indica la fecha de nacimiento.';
  } else if (ageStatus === null) {
    errors.participant_birth_date = 'Indica una fecha de nacimiento válida y no futura.';
  }

  if (!fields.contact_phone.trim()) {
    errors.contact_phone = 'Indica un teléfono de contacto.';
  } else if (!/^\+?[0-9][0-9\s().-]{6,24}$/.test(fields.contact_phone.trim())) {
    errors.contact_phone = 'Indica un teléfono de contacto válido.';
  }

  if (!fields.contact_email.trim()) {
    errors.contact_email = 'Indica un correo electrónico de contacto.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.contact_email.trim())) {
    errors.contact_email = 'Indica un correo electrónico válido.';
  }

  if (
    fields.school_level_id
    && !levels.some((level) => String(level.id) === fields.school_level_id)
  ) {
    errors.school_level_id = 'Selecciona uno de los niveles disponibles.';
  }

  if (ageStatus === 'minor') {
    if (!fields.guardian_name.trim()) {
      errors.guardian_name = 'Indica el nombre completo del representante.';
    }
    if (!fields.guardian_relationship.trim()) {
      errors.guardian_relationship = 'Indica la relación con el participante.';
    }
    if (
      identityAuthorization?.enabled
      && fields.public_identity_mode !== 'anonymous'
      && !fields.guardian_authority_declared
    ) {
      errors.guardian_authority_declared = 'Debes confirmar que ejerces la patria potestad o tutela.';
    }
  }

  if (!fields.privacy_acknowledged) {
    errors.privacy_acknowledged = 'Debes confirmar que has leído la información de privacidad de la inscripción.';
  }

  return { errors, ageStatus };
};

const FieldError = ({ field, errors }) => (
  errors[field] ? (
    <span id={`${field}-error`} className={styles.fieldError}>
      {errors[field]}
    </span>
  ) : null
);

export const SchoolEnrollmentForm = ({
  levels,
  reloadOverview,
  identityAuthorization,
  privacyNotice = schoolEnrollmentNotice,
}) => {
  const [fields, setFields] = useState(emptyFields);
  const [errors, setErrors] = useState({});
  const [submitState, setSubmitState] = useState('idle');
  const [generalMessage, setGeneralMessage] = useState(null);
  const fieldRefs = useRef({});
  const pendingFocus = useRef(null);
  const messageRef = useRef(null);
  const successRef = useRef(null);
  const submittingRef = useRef(false);
  const ageStatus = useMemo(
    () => getParticipantAgeStatus(fields.participant_birth_date),
    [fields.participant_birth_date],
  );
  const isUnavailable = submitState === 'unavailable';
  const isSubmitting = submitState === 'submitting';

  const focusFirstInvalidField = (nextErrors) => {
    pendingFocus.current = fieldOrder.find((field) => nextErrors[field]) ?? null;
  };

  useEffect(() => {
    if (pendingFocus.current) {
      fieldRefs.current[pendingFocus.current]?.focus();
      pendingFocus.current = null;
    }
  }, [errors]);

  useEffect(() => {
    if (submitState === 'success') {
      successRef.current?.focus();
    } else if (generalMessage && submitState !== 'invalid') {
      messageRef.current?.focus();
    }
  }, [generalMessage, submitState]);

  const handleChange = (event) => {
    const { checked, name, type, value } = event.target;

    setFields((current) => {
      const next = { ...current, [name]: type === 'checkbox' ? checked : value };

      if (
        name === 'participant_birth_date'
        && getParticipantAgeStatus(value) !== 'minor'
      ) {
        next.guardian_name = '';
        next.guardian_relationship = '';
        next.public_identity_mode = 'anonymous';
        next.guardian_authority_declared = false;
      }

      return next;
    });
  };

  const fieldProps = (name) => ({
    name,
    id: name,
    ...(typeof fields[name] === 'boolean'
      ? { checked: fields[name] }
      : { value: fields[name] }),
    onChange: handleChange,
    ref: (element) => {
      fieldRefs.current[name] = element;
    },
    'aria-invalid': Boolean(errors[name]),
    'aria-describedby': errors[name] ? `${name}-error` : undefined,
  });

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (isSubmitting || isUnavailable || submittingRef.current) {
      return;
    }

    const validation = validateSchoolEnrollment(
      fields,
      levels,
      identityAuthorization,
      new Date(),
    );
    setErrors(validation.errors);
    setGeneralMessage(null);

    if (Object.keys(validation.errors).length > 0) {
      setSubmitState('invalid');
      focusFirstInvalidField(validation.errors);
      return;
    }

    const payload = {
      participant_name: fields.participant_name.trim(),
      participant_birth_date: fields.participant_birth_date,
      contact_phone: fields.contact_phone.trim(),
      contact_email: fields.contact_email.trim(),
      privacy_acknowledged: true,
      privacy_notice_id: privacyNotice.id,
      privacy_notice_version: privacyNotice.version,
      website: fields.website,
      ...(fields.school_level_id
        ? { school_level_id: Number(fields.school_level_id) }
        : {}),
      ...(validation.ageStatus === 'minor'
        ? {
            guardian_name: fields.guardian_name.trim(),
            guardian_relationship: fields.guardian_relationship.trim(),
          }
        : {}),
      ...(validation.ageStatus === 'minor'
        && identityAuthorization?.enabled
        && minorPublicIdentityNotice
        && identityAuthorization.notice_version === minorPublicIdentityNotice.version
        ? {
            public_identity_authorization: {
              mode: fields.public_identity_mode,
              notice_version: minorPublicIdentityNotice.version,
              ...(fields.public_identity_mode !== 'anonymous'
                ? { guardian_authority_declared: true }
                : {}),
            },
          }
        : {}),
    };

    submittingRef.current = true;
    setSubmitState('submitting');

    try {
      await schoolService.createEnrollment(payload);
      setFields(emptyFields);
      setErrors({});
      setSubmitState('success');
      setGeneralMessage('La solicitud de inscripción se ha recibido correctamente.');
    } catch (error) {
      submittingRef.current = false;
      const status = error.response?.status;

      if (status === 422) {
        const backendErrors = error.response?.data?.errors ?? {};
        const mappedErrors = {
          ...backendErrors,
          guardian_authority_declared:
            backendErrors['public_identity_authorization.guardian_authority_declared'],
        };
        const nextErrors = Object.fromEntries(
          fieldOrder
            .map((field) => [field, firstMessage(mappedErrors[field])])
            .filter(([, message]) => message),
        );
        const payloadMessage = firstMessage(backendErrors.payload);

        setErrors(nextErrors);
        setSubmitState('invalid');
        setGeneralMessage(
          payloadMessage
          ?? (Object.keys(nextErrors).length > 0
            ? 'Revisa los campos indicados.'
            : 'No se ha podido validar la solicitud. Revisa los datos.'),
        );
        focusFirstInvalidField(nextErrors);
      } else if (status === 409) {
        setSubmitState('unavailable');
        setGeneralMessage(
          error.response?.data?.message
          ?? 'Las inscripciones no están disponibles en este momento.',
        );
      } else if (status === 429) {
        setSubmitState('rate-limited');
        setGeneralMessage(
          'Se han realizado demasiados intentos. Espera un momento antes de volver a intentarlo.',
        );
      } else {
        setSubmitState('error');
        setGeneralMessage(
          'No se ha podido enviar la solicitud. Comprueba tu conexión e inténtalo de nuevo.',
        );
      }
    }
  };

  const refreshAvailability = async () => {
    const result = await reloadOverview();

    if (result.ok && result.data?.enrollment_status === 'open') {
      setSubmitState('idle');
      setGeneralMessage(null);
    }
  };

  if (submitState === 'success') {
    return (
      <div ref={successRef} className={styles.successState} role="status" tabIndex="-1">
        <h3>Solicitud recibida</h3>
        <p>{generalMessage}</p>
      </div>
    );
  }

  return (
    <form className={styles.form} noValidate onSubmit={handleSubmit}>
      {generalMessage ? (
        <div ref={messageRef} className={styles.formMessage} role="alert" tabIndex="-1">
          <p>{generalMessage}</p>
          {isUnavailable ? (
            <button type="button" className={styles.secondaryButton} onClick={refreshAvailability}>
              Volver a comprobar disponibilidad
            </button>
          ) : null}
        </div>
      ) : null}

      <fieldset disabled={isSubmitting || isUnavailable}>
        <legend>Participante</legend>
        <div className={styles.fieldGrid}>
          <div className={styles.field}>
            <label htmlFor="participant_name">
              {fieldLabels.participant_name} <span aria-hidden="true">*</span>
            </label>
            <input {...fieldProps('participant_name')} type="text" autoComplete="name" required />
            <FieldError field="participant_name" errors={errors} />
          </div>
          <div className={styles.field}>
            <label htmlFor="participant_birth_date">
              {fieldLabels.participant_birth_date} <span aria-hidden="true">*</span>
            </label>
            <input
              {...fieldProps('participant_birth_date')}
              type="date"
              autoComplete="bday"
              required
            />
            <FieldError field="participant_birth_date" errors={errors} />
          </div>
          {levels.length > 0 ? (
            <div className={styles.field}>
              <label htmlFor="school_level_id">{fieldLabels.school_level_id} (opcional)</label>
              <select {...fieldProps('school_level_id')}>
                <option value="">Sin preferencia</option>
                {levels.map((level) => (
                  <option key={level.id} value={level.id}>{level.name}</option>
                ))}
              </select>
              <FieldError field="school_level_id" errors={errors} />
            </div>
          ) : null}
        </div>
      </fieldset>

      <fieldset disabled={isSubmitting || isUnavailable}>
        <legend>Contacto</legend>
        <div className={styles.fieldGrid}>
          <div className={styles.field}>
            <label htmlFor="contact_phone">
              {fieldLabels.contact_phone} <span aria-hidden="true">*</span>
            </label>
            <input {...fieldProps('contact_phone')} type="tel" autoComplete="tel" required />
            <FieldError field="contact_phone" errors={errors} />
          </div>
          <div className={styles.field}>
            <label htmlFor="contact_email">
              {fieldLabels.contact_email} <span aria-hidden="true">*</span>
            </label>
            <input {...fieldProps('contact_email')} type="email" autoComplete="email" required />
            <FieldError field="contact_email" errors={errors} />
          </div>
        </div>
      </fieldset>

      {ageStatus === 'minor' ? (
        <fieldset disabled={isSubmitting || isUnavailable}>
          <legend>Representante</legend>
          <div className={styles.fieldGrid}>
            <div className={styles.field}>
              <label htmlFor="guardian_name">
                {fieldLabels.guardian_name} <span aria-hidden="true">*</span>
              </label>
              <input {...fieldProps('guardian_name')} type="text" autoComplete="name" required />
              <FieldError field="guardian_name" errors={errors} />
            </div>
            <div className={styles.field}>
              <label htmlFor="guardian_relationship">
                {fieldLabels.guardian_relationship} <span aria-hidden="true">*</span>
              </label>
              <input
                {...fieldProps('guardian_relationship')}
                type="text"
                autoComplete="off"
                required
              />
              <FieldError field="guardian_relationship" errors={errors} />
            </div>
          </div>
        </fieldset>
      ) : null}

      <fieldset disabled={isSubmitting || isUnavailable}>
        <legend>Privacidad de la inscripción</legend>
        <aside
          className={styles.privacyNotice}
          aria-labelledby="school-enrollment-privacy-title"
        >
          <h3 id="school-enrollment-privacy-title">Información sobre protección de datos</h3>
          <LegalRenderer blocks={privacyNotice.blocks} />
          <p className={styles.noticeText}>
            Aviso {privacyNotice.id}, versión {privacyNotice.version}. Consulta la{' '}
            <a href={privacyNotice.privacyUrl} target="_blank" rel="noopener noreferrer">
              Política de privacidad
              <span className={styles.srOnly}> (se abre en una pestaña nueva)</span>
            </a>.
          </p>
        </aside>
        <div className={styles.checkField}>
          <input {...fieldProps('privacy_acknowledged')} type="checkbox" required />
          <label htmlFor="privacy_acknowledged">
            He leído la información de privacidad de la inscripción
            {' '}<span aria-hidden="true">*</span>
          </label>
        </div>
        <FieldError field="privacy_acknowledged" errors={errors} />
      </fieldset>

      <div className={styles.honeypot} aria-hidden="true">
        <label htmlFor="website">Sitio web</label>
        <input
          {...fieldProps('website')}
          type="text"
          autoComplete="off"
          tabIndex="-1"
        />
      </div>

      {ageStatus === 'minor'
        && identityAuthorization?.enabled
        && minorPublicIdentityNotice
        && identityAuthorization.notice_version === minorPublicIdentityNotice.version ? (
          <fieldset disabled={isSubmitting || isUnavailable}>
            <legend>Identidad pública en competición (opcional)</legend>
            <p className={styles.noticeText}>
              {minorPublicIdentityNotice.owner} es responsable. Esta decisión es
              independiente de la inscripción. Se aplica sólo a
              calendarios, partidos, resultados, clasificaciones, rankings e histórico
              deportivo. Requiere confirmación por correo y revisión del club. Aviso
              {' '}{minorPublicIdentityNotice.id}, versión {minorPublicIdentityNotice.version}.
            </p>
            <div className={styles.radioGroup} role="radiogroup" aria-label="Modo de identidad pública">
              <label>
                <input
                  type="radio"
                  name="public_identity_mode"
                  value="anonymous"
                  checked={fields.public_identity_mode === 'anonymous'}
                  onChange={handleChange}
                />
                No autorizar identidad individual: mostrar “Participante”
              </label>
              <label>
                <input
                  type="radio"
                  name="public_identity_mode"
                  value="alias"
                  checked={fields.public_identity_mode === 'alias'}
                  onChange={handleChange}
                />
                Autorizar sólo el alias deportivo; sin alias se mostrará “Participante”
              </label>
              <label>
                <input
                  type="radio"
                  name="public_identity_mode"
                  value="name_initial"
                  checked={fields.public_identity_mode === 'name_initial'}
                  onChange={handleChange}
                />
                Autorizar nombres de pila e inicial del primer apellido; la inicial puede
                permitir identificar al menor
              </label>
            </div>
            {fields.public_identity_mode !== 'anonymous' ? (
              <>
                <div className={styles.checkField}>
                  <input
                    {...fieldProps('guardian_authority_declared')}
                    type="checkbox"
                    required
                  />
                  <label htmlFor="guardian_authority_declared">
                    Declaro que ejerzo la patria potestad o tutela y que he leído el aviso
                    {' '}<span aria-hidden="true">*</span>
                  </label>
                </div>
                <FieldError field="guardian_authority_declared" errors={errors} />
              </>
            ) : null}
            <p className={styles.noticeText}>
              No se publicarán correo, teléfono, nacimiento, datos del representante ni
              perfil privado. Puedes retirar la autorización a través del correo indicado
              en la <a href="/legal/privacidad">Política de privacidad</a>. Si no autorizas,
              la identidad se mostrará como “Participante” sin afectar a la inscripción o
              participación deportiva.
            </p>
          </fieldset>
        ) : null}

      <p className={styles.requiredHelp}>
        Los campos marcados con <span aria-hidden="true">*</span> son obligatorios.
      </p>
      <button
        type="submit"
        className={styles.submitButton}
        disabled={isSubmitting || isUnavailable}
      >
        {isSubmitting ? 'Enviando solicitud…' : 'Enviar solicitud'}
      </button>
      {isSubmitting ? (
        <p className={styles.srOnly} role="status" aria-live="polite">
          Enviando la solicitud de inscripción.
        </p>
      ) : null}
    </form>
  );
};
