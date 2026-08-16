import { useEffect, useMemo, useRef, useState } from 'react';
import { useProfilePhoto } from './useProfilePhoto';
import styles from './ProfilePhotoCard.module.css';

const ALLOWED_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const MAX_SIZE_BYTES = 3072 * 1024;

const initialsFor = (name, lastname) => {
  const first = name?.trim()?.charAt(0) ?? '';
  const last = lastname?.trim()?.charAt(0) ?? '';

  return `${first}${last}`.toLocaleUpperCase('es-ES');
};

export const ProfilePhotoCard = ({ user, onProfilePhotoChange }) => {
  const [selectedFile, setSelectedFile] = useState(null);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [localError, setLocalError] = useState(null);
  const fileInputRef = useRef(null);
  const previewUrlRef = useRef(null);
  const profilePhoto = user?.profile_photo ?? null;
  const fullName = [user?.name, user?.lastname].filter(Boolean).join(' ').trim();
  const initials = useMemo(
    () => initialsFor(user?.name, user?.lastname),
    [user?.lastname, user?.name],
  );
  const {
    imageUrl,
    isLoading,
    isMutating,
    error,
    feedback,
    upload,
    remove,
    retry,
    handleImageFailure,
  } = useProfilePhoto({ profilePhoto, onProfilePhotoChange });

  const clearSelection = () => {
    if (previewUrlRef.current) {
      URL.revokeObjectURL(previewUrlRef.current);
      previewUrlRef.current = null;
    }

    setPreviewUrl(null);
    setSelectedFile(null);
    setLocalError(null);

    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  useEffect(() => () => {
    if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
  }, []);

  const handleFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    clearSelection();

    if (!file) return;

    if (!ALLOWED_TYPES.has(file.type)) {
      setLocalError('Selecciona una imagen JPEG, PNG o WebP.');
      return;
    }

    if (file.size > MAX_SIZE_BYTES) {
      setLocalError('La foto no puede superar los 3 MB.');
      return;
    }

    const nextPreviewUrl = URL.createObjectURL(file);
    previewUrlRef.current = nextPreviewUrl;
    setPreviewUrl(nextPreviewUrl);
    setSelectedFile(file);
  };

  const handleUpload = async () => {
    if (!selectedFile) return;

    if (await upload(selectedFile)) clearSelection();
  };

  const handleRemove = async () => {
    if (!window.confirm('¿Quieres eliminar tu foto de perfil?')) return;

    if (await remove()) clearSelection();
  };

  const handleVisibleImageError = () => {
    if (previewUrl) {
      clearSelection();
      setLocalError('No se pudo mostrar la vista previa seleccionada.');
      return;
    }

    handleImageFailure();
  };

  const visibleImageUrl = previewUrl || imageUrl;
  const visibleError = localError || error;
  const inputDescription = visibleError
    ? 'profile-photo-help profile-photo-error'
    : 'profile-photo-help';

  return (
    <section className={styles.section} aria-labelledby="profile-photo-title">
      <div className={styles.avatarFrame}>
        {visibleImageUrl ? (
          <img
            className={styles.avatar}
            src={visibleImageUrl}
            alt={`Foto de perfil de ${fullName || 'tu cuenta'}`}
            onError={handleVisibleImageError}
          />
        ) : (
          <div className={styles.fallback} role="img" aria-label="Sin foto de perfil">
            {initials || <span aria-hidden="true">●</span>}
          </div>
        )}
        {isLoading ? <span className={styles.loadingBadge}>Cargando…</span> : null}
      </div>

      <div className={styles.content}>
        <div>
          <h3 id="profile-photo-title" className={styles.title}>Foto de perfil</h3>
          <p id="profile-photo-help" className={styles.help}>
            JPEG, PNG o WebP · máximo 3 MB. Es privada y no se publica en competición.
          </p>
        </div>

        <div className={styles.actions}>
          <label className={styles.fileButton}>
            {profilePhoto ? 'Cambiar foto' : 'Subir foto'}
            <input
              ref={fileInputRef}
              id="profile-photo-input"
              className={styles.fileInput}
              type="file"
              name="photo"
              accept="image/jpeg,image/png,image/webp"
              aria-describedby={inputDescription}
              disabled={isMutating}
              onChange={handleFileChange}
            />
          </label>

          {selectedFile ? (
            <>
              <button
                className={styles.primaryButton}
                type="button"
                disabled={isMutating}
                onClick={handleUpload}
              >
                {isMutating ? 'Guardando…' : 'Guardar foto'}
              </button>
              <button
                className={styles.secondaryButton}
                type="button"
                disabled={isMutating}
                onClick={clearSelection}
              >
                Cancelar
              </button>
            </>
          ) : null}

          {profilePhoto ? (
            <button
              className={styles.dangerButton}
              type="button"
              disabled={isMutating}
              onClick={handleRemove}
            >
              {isMutating ? 'Eliminando…' : 'Eliminar foto'}
            </button>
          ) : null}

          {error && profilePhoto && !selectedFile ? (
            <button className={styles.secondaryButton} type="button" onClick={retry}>
              Reintentar carga
            </button>
          ) : null}
        </div>

        {previewUrl ? <p className={styles.previewNotice}>Vista previa pendiente de guardar.</p> : null}
        {visibleError ? <p id="profile-photo-error" className={styles.error} role="alert">{visibleError}</p> : null}
        {feedback ? <p className={styles.feedback} role="status">{feedback}</p> : null}
      </div>
    </section>
  );
};
