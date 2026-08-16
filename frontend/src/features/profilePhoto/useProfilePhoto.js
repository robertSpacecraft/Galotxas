import { useCallback, useEffect, useRef, useState } from 'react';
import { profilePhotoService } from './profilePhotoService';

const mutationErrorMessage = (error) => {
  const fieldMessage = error?.response?.data?.errors?.photo?.[0];

  if (typeof fieldMessage === 'string' && fieldMessage.trim() !== '') {
    return fieldMessage;
  }

  switch (error?.response?.status) {
    case 422:
      return 'La foto seleccionada no es válida.';
    case 429:
      return 'Has realizado demasiados intentos. Espera un momento antes de continuar.';
    case 503:
      return 'La foto de perfil no está disponible temporalmente.';
    default:
      return 'No se pudo actualizar la foto de perfil. Inténtalo de nuevo.';
  }
};

export const useProfilePhoto = ({ profilePhoto, onProfilePhotoChange }) => {
  const [imageUrl, setImageUrl] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isMutating, setIsMutating] = useState(false);
  const [error, setError] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const [reloadToken, setReloadToken] = useState(0);
  const imageUrlRef = useRef(null);
  const loadedReferenceRef = useRef(null);
  const referenceUrl = profilePhoto?.url ?? null;

  const clearImage = useCallback(() => {
    if (imageUrlRef.current) {
      URL.revokeObjectURL(imageUrlRef.current);
      imageUrlRef.current = null;
    }

    loadedReferenceRef.current = null;
    setImageUrl(null);
  }, []);

  const replaceImage = useCallback((blob, reference) => {
    const nextUrl = URL.createObjectURL(blob);

    if (imageUrlRef.current) {
      URL.revokeObjectURL(imageUrlRef.current);
    }

    imageUrlRef.current = nextUrl;
    loadedReferenceRef.current = reference;
    setImageUrl(nextUrl);
  }, []);

  const loadImage = useCallback(async ({ signal, reference }) => {
    const blob = await profilePhotoService.download({ signal });
    replaceImage(blob, reference);
  }, [replaceImage]);

  useEffect(() => {
    if (!referenceUrl) {
      clearImage();
      setIsLoading(false);
      return undefined;
    }

    if (loadedReferenceRef.current === referenceUrl && imageUrlRef.current) {
      return undefined;
    }

    const controller = new AbortController();
    let active = true;
    setIsLoading(true);
    setError(null);

    loadImage({ signal: controller.signal, reference: referenceUrl })
      .catch(() => {
        if (!active || controller.signal.aborted) return;

        clearImage();
        setError('No se pudo mostrar la foto de perfil. Puedes volver a intentarlo.');
      })
      .finally(() => {
        if (active) setIsLoading(false);
      });

    return () => {
      active = false;
      controller.abort();
    };
  }, [clearImage, loadImage, referenceUrl, reloadToken]);

  useEffect(() => () => {
    if (imageUrlRef.current) {
      URL.revokeObjectURL(imageUrlRef.current);
      imageUrlRef.current = null;
    }
  }, []);

  const upload = useCallback(async (file) => {
    setIsMutating(true);
    setError(null);
    setFeedback(null);

    try {
      const nextProfilePhoto = await profilePhotoService.upload(file);
      onProfilePhotoChange(nextProfilePhoto);

      try {
        await loadImage({ reference: nextProfilePhoto.url });
        setFeedback('Foto de perfil actualizada correctamente.');
      } catch {
        clearImage();
        setError('La foto se guardó, pero no se pudo mostrar. Puedes volver a intentarlo.');
      }

      return true;
    } catch (requestError) {
      setError(mutationErrorMessage(requestError));
      return false;
    } finally {
      setIsMutating(false);
    }
  }, [clearImage, loadImage, onProfilePhotoChange]);

  const remove = useCallback(async () => {
    setIsMutating(true);
    setError(null);
    setFeedback(null);

    try {
      const nextProfilePhoto = await profilePhotoService.remove();
      clearImage();
      onProfilePhotoChange(nextProfilePhoto);
      setFeedback('Foto de perfil eliminada correctamente.');
      return true;
    } catch (requestError) {
      setError(mutationErrorMessage(requestError));
      return false;
    } finally {
      setIsMutating(false);
    }
  }, [clearImage, onProfilePhotoChange]);

  const retry = useCallback(() => {
    setError(null);
    setReloadToken((current) => current + 1);
  }, []);

  const handleImageFailure = useCallback(() => {
    clearImage();
    setError('No se pudo mostrar la foto de perfil. Puedes volver a intentarlo.');
  }, [clearImage]);

  return {
    imageUrl,
    isLoading,
    isMutating,
    error,
    feedback,
    upload,
    remove,
    retry,
    handleImageFailure,
  };
};
