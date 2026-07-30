import { useEffect, useMemo, useRef, useState } from 'react';
import { getParticipantAgeStatus } from './schoolDate';
import { schoolService } from './schoolService';
import styles from './SchoolPage.module.css';

const emptyFields = {
  participant_name: '',
  participant_birth_date: '',
  school_level_id: '',
  contact_phone: '',
  contact_email: '',
  guardian_name: '',
  guardian_relationship: '',
};

const fieldOrder = [
  'participant_name',
  'participant_birth_date',
  'school_level_id',
  'contact_phone',
  'contact_email',
  'guardian_name',
  'guardian_relationship',
];

const fieldLabels = {
  participant_name: 'Nombre completo del participante',
  participant_birth_date: 'Fecha de nacimiento',
  school_level_id: 'Nivel solicitado',
  contact_phone: 'Teléfono de contacto',
  contact_email: 'Correo electrónico de contacto',
  guardian_name: 'Nombre completo del representante',
  guardian_relationship: 'Relación con el participante',
};

const firstMessage = (value) => (
  Array.isArray(value) ? value.find((message) => typeof message === 'string') : null
);

const validateSchoolEnrollment = (fields, levels, referenceDate = new Date()) => {
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

export const SchoolEnrollmentForm = ({ levels, reloadOverview }) => {
  const [fields, setFields] = useState(emptyFields);
  const [errors, setErrors] = useState({});
  const [submitState, setSubmitState] = useState('idle');
  const [generalMessage, setGeneralMessage] = useState(null);
  const fieldRefs = useRef({});
  const pendingFocus = useRef(null);
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

  const handleChange = (event) => {
    const { name, value } = event.target;

    setFields((current) => {
      const next = { ...current, [name]: value };

      if (
        name === 'participant_birth_date'
        && getParticipantAgeStatus(value) !== 'minor'
      ) {
        next.guardian_name = '';
        next.guardian_relationship = '';
      }

      return next;
    });
  };

  const fieldProps = (name) => ({
    name,
    id: name,
    value: fields[name],
    onChange: handleChange,
    ref: (element) => {
      fieldRefs.current[name] = element;
    },
    'aria-invalid': Boolean(errors[name]),
    'aria-describedby': errors[name] ? `${name}-error` : undefined,
  });

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (isSubmitting || isUnavailable) {
      return;
    }

    const validation = validateSchoolEnrollment(fields, levels, new Date());
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
      ...(fields.school_level_id
        ? { school_level_id: Number(fields.school_level_id) }
        : {}),
      ...(validation.ageStatus === 'minor'
        ? {
            guardian_name: fields.guardian_name.trim(),
            guardian_relationship: fields.guardian_relationship.trim(),
          }
        : {}),
    };

    setSubmitState('submitting');

    try {
      await schoolService.createEnrollment(payload);
      setFields(emptyFields);
      setErrors({});
      setSubmitState('success');
      setGeneralMessage('La solicitud de inscripción se ha recibido correctamente.');
    } catch (error) {
      const status = error.response?.status;

      if (status === 422) {
        const backendErrors = error.response?.data?.errors ?? {};
        const nextErrors = Object.fromEntries(
          fieldOrder
            .map((field) => [field, firstMessage(backendErrors[field])])
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

    if (result.ok && result.data?.enrollments_open) {
      setSubmitState('idle');
      setGeneralMessage(null);
    }
  };

  if (submitState === 'success') {
    return (
      <div className={styles.successState} role="status" tabIndex="-1">
        <h3>Solicitud recibida</h3>
        <p>{generalMessage}</p>
      </div>
    );
  }

  return (
    <form className={styles.form} noValidate onSubmit={handleSubmit}>
      {generalMessage ? (
        <div className={styles.formMessage} role="alert">
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
