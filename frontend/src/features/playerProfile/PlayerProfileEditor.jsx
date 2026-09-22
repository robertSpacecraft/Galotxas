import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { accountProfileNotice } from '../legal/formNoticeRepository';
import styles from './PlayerProfileEditor.module.css';

const NICKNAME_HELP = 'Apodo deportivo por el que te conocen en la pista. No uses tu nombre habitual salvo que también sea tu apodo deportivo.';

const statusMessages = Object.freeze({
  adult_alias: 'Se muestra tu apodo deportivo.',
  adult_name_initial: 'Se muestran tus nombres de pila y la inicial del primer apellido.',
  adult_no_publishable_identity: 'No hay una identidad adulta publicable y se muestra “Participante”.',
  birth_date_unknown: 'Falta la fecha de nacimiento y se muestra “Participante”.',
  minor_effective_authorization: 'Existe una autorización efectiva y se aplica su modalidad aprobada.',
  minor_no_effective_authorization: 'No existe una autorización efectiva y se muestra “Participante”.',
});

const snapshotFrom = (player) => ({
  nickname: player?.nickname ?? '',
  dominant_hand: player?.dominant_hand ?? '',
  license_number: player?.license_number ?? '',
  birth_date: player?.birth_date ?? '',
});

export function PlayerProfileEditor({ player, generalDeclarationRequired, onSave }) {
  const snapshot = useMemo(() => snapshotFrom(player), [player]);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState(snapshot);
  const [generalAccepted, setGeneralAccepted] = useState(false);
  const [birthDateConfirmed, setBirthDateConfirmed] = useState(false);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [savedMessage, setSavedMessage] = useState('');
  const editButtonRef = useRef(null);
  const fieldRefs = useRef({});
  const errorSummaryRef = useRef(null);

  useEffect(() => {
    if (!editing) setForm(snapshot);
  }, [editing, snapshot]);

  const birthDateChanged = form.birth_date !== snapshot.birth_date;

  const beginEditing = () => {
    setForm(snapshot);
    setErrors({});
    setSavedMessage('');
    setGeneralAccepted(false);
    setBirthDateConfirmed(false);
    setEditing(true);
  };

  const cancelEditing = () => {
    setForm(snapshot);
    setErrors({});
    setEditing(false);
    requestAnimationFrame(() => editButtonRef.current?.focus());
  };

  const setValue = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
    setErrors((current) => ({ ...current, [name]: undefined, payload: undefined }));
  };

  const submit = async (event) => {
    event.preventDefault();
    if (saving) return;

    setSaving(true);
    setErrors({});
    setSavedMessage('');

    const payload = {
      nickname: form.nickname || null,
      dominant_hand: form.dominant_hand || null,
      license_number: form.license_number || null,
      birth_date: form.birth_date || null,
    };

    if (generalDeclarationRequired || birthDateChanged) {
      payload.profile_notice_id = accountProfileNotice?.id;
      payload.profile_notice_version = accountProfileNotice?.version;
    }
    if (generalDeclarationRequired) {
      payload.profile_declaration_accepted = generalAccepted;
    }
    if (birthDateChanged) {
      payload.birth_date_confirmed = birthDateConfirmed;
    }

    try {
      await onSave(payload);
      setEditing(false);
      setSavedMessage('Perfil actualizado correctamente.');
      requestAnimationFrame(() => editButtonRef.current?.focus());
    } catch (error) {
      const responseErrors = error.response?.data?.errors || {};
      const normalizedErrors = Object.fromEntries(
        Object.entries(responseErrors).map(([field, messages]) => [
          field,
          Array.isArray(messages) ? messages[0] : messages,
        ]),
      );
      if (Object.keys(normalizedErrors).length === 0) {
        normalizedErrors.payload = error.response?.data?.message || 'No se ha podido actualizar el perfil.';
      }
      setErrors(normalizedErrors);

      requestAnimationFrame(() => {
        const firstInvalid = ['nickname', 'dominant_hand', 'license_number', 'birth_date', 'profile_declaration_accepted', 'birth_date_confirmed']
          .find((field) => normalizedErrors[field]);
        if (firstInvalid) fieldRefs.current[firstInvalid]?.focus();
        else errorSummaryRef.current?.focus();
      });
    } finally {
      setSaving(false);
    }
  };

  const errorFor = (field) => errors[field];
  const describedBy = (field, helperId) => [
    helperId,
    errorFor(field) ? `profile-${field}-error` : null,
  ].filter(Boolean).join(' ') || undefined;

  return (
    <section className={styles.editor} aria-labelledby="profile-editor-title">
      <div className={styles.headingRow}>
        <h3 id="profile-editor-title">Autogestión del perfil</h3>
        {!editing && (
          <button ref={editButtonRef} type="button" className={styles.editButton} onClick={beginEditing}>
            Editar perfil
          </button>
        )}
      </div>

      {player?.public_identity && (
        <div className={styles.diagnostic}>
          <strong>Identidad pública efectiva:</strong> {player.public_identity.display_name}.{' '}
          {statusMessages[player.public_identity.status] || 'Estado no disponible.'}
        </div>
      )}

      {savedMessage && <p className={styles.successMessage} role="status">{savedMessage}</p>}

      {editing && (
        <form onSubmit={submit} noValidate className={styles.form}>
          {Object.keys(errors).length > 0 && (
            <div ref={errorSummaryRef} tabIndex={-1} role="alert" aria-live="assertive" className={styles.errorSummary}>
              <strong>Revisa los campos indicados.</strong>
              <ul>
                {Object.entries(errors).map(([field, message]) => (
                  <li key={field}>{message}</li>
                ))}
              </ul>
            </div>
          )}

          <div className={styles.grid}>
            <div className={styles.field}>
              <label htmlFor="profile-nickname">Apodo deportivo</label>
              <input
                ref={(element) => { fieldRefs.current.nickname = element; }}
                id="profile-nickname"
                name="nickname"
                value={form.nickname}
                onChange={setValue}
                aria-invalid={Boolean(errorFor('nickname'))}
                aria-describedby={describedBy('nickname', 'profile-nickname-help')}
              />
              <small id="profile-nickname-help">{NICKNAME_HELP}</small>
              {errorFor('nickname') && <span id="profile-nickname-error" className={styles.fieldError}>{errorFor('nickname')}</span>}
            </div>

            <div className={styles.field}>
              <label htmlFor="profile-dominant-hand">Mano dominante</label>
              <select
                ref={(element) => { fieldRefs.current.dominant_hand = element; }}
                id="profile-dominant-hand"
                name="dominant_hand"
                value={form.dominant_hand}
                onChange={setValue}
                aria-invalid={Boolean(errorFor('dominant_hand'))}
                aria-describedby={describedBy('dominant_hand')}
              >
                <option value="">Sin especificar</option>
                <option value="right">Diestro</option>
                <option value="left">Zurdo</option>
                <option value="both">Ambidiestro</option>
              </select>
              {errorFor('dominant_hand') && <span id="profile-dominant_hand-error" className={styles.fieldError}>{errorFor('dominant_hand')}</span>}
            </div>

            <div className={styles.field}>
              <label htmlFor="profile-license-number">Número de licencia</label>
              <input
                ref={(element) => { fieldRefs.current.license_number = element; }}
                id="profile-license-number"
                name="license_number"
                value={form.license_number}
                onChange={setValue}
                aria-invalid={Boolean(errorFor('license_number'))}
                aria-describedby={describedBy('license_number', 'profile-license-help')}
              />
              <small id="profile-license-help">Dato informativo no verificado por la federación.</small>
              {errorFor('license_number') && <span id="profile-license_number-error" className={styles.fieldError}>{errorFor('license_number')}</span>}
            </div>

            <div className={styles.field}>
              <label htmlFor="profile-birth-date">Fecha de nacimiento</label>
              <input
                ref={(element) => { fieldRefs.current.birth_date = element; }}
                id="profile-birth-date"
                name="birth_date"
                type="date"
                autoComplete="bday"
                value={form.birth_date}
                onChange={setValue}
                aria-invalid={Boolean(errorFor('birth_date'))}
                aria-describedby={describedBy('birth_date', 'profile-birth-date-help')}
              />
              <small id="profile-birth-date-help">El backend aplica las restricciones de edad y autorización.</small>
              {errorFor('birth_date') && <span id="profile-birth_date-error" className={styles.fieldError}>{errorFor('birth_date')}</span>}
            </div>
          </div>

          {generalDeclarationRequired && (
            <label className={styles.checkboxRow}>
              <input
                ref={(element) => { fieldRefs.current.profile_declaration_accepted = element; }}
                type="checkbox"
                checked={generalAccepted}
                onChange={(event) => setGeneralAccepted(event.target.checked)}
                aria-invalid={Boolean(errorFor('profile_declaration_accepted'))}
                aria-describedby={errorFor('profile_declaration_accepted') ? 'profile-profile_declaration_accepted-error' : undefined}
              />
              <span>He leído la <Link to="/legal/privacidad">Política de Privacidad</Link> y declaro que los datos facilitados son exactos y veraces.</span>
              {errorFor('profile_declaration_accepted') && <span id="profile-profile_declaration_accepted-error" className={styles.fieldError}>{errorFor('profile_declaration_accepted')}</span>}
            </label>
          )}

          {birthDateChanged && (
            <label className={styles.checkboxRow}>
              <input
                ref={(element) => { fieldRefs.current.birth_date_confirmed = element; }}
                type="checkbox"
                checked={birthDateConfirmed}
                onChange={(event) => setBirthDateConfirmed(event.target.checked)}
                aria-invalid={Boolean(errorFor('birth_date_confirmed'))}
                aria-describedby={errorFor('birth_date_confirmed') ? 'profile-birth_date_confirmed-error' : undefined}
              />
              <span>Confirmo que la fecha de nacimiento indicada es correcta.</span>
              {errorFor('birth_date_confirmed') && <span id="profile-birth_date_confirmed-error" className={styles.fieldError}>{errorFor('birth_date_confirmed')}</span>}
            </label>
          )}

          <div className={styles.actions}>
            <button type="submit" disabled={saving || !accountProfileNotice}>
              {saving ? 'Guardando…' : 'Guardar'}
            </button>
            <button type="button" disabled={saving} onClick={cancelEditing}>Cancelar</button>
          </div>
        </form>
      )}
    </section>
  );
}
