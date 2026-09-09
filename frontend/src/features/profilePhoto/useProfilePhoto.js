import { useCallback, useEffect, useRef, useState } from 'react';
import { profilePhotoService } from './profilePhotoService';

const renderedAvatarWidth = () => {
  const rootFontSize = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

  return 7 * rootFontSize;
};

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
  const loadedVariantWidthRef = useRef(null);
  const masterOnlyReferenceRef = useRef(null);
  const referenceUrl = profilePhoto?.url ?? null;

  const clearImage = useCallback(() => {
    if (imageUrlRef.current) {
      URL.revokeObjectURL(imageUrlRef.current);
      imageUrlRef.current = null;
    }

    loadedReferenceRef.current = null;
    loadedVariantWidthRef.current = null;
    setImageUrl(null);
  }, []);

  const replaceImage = useCallback((blob, reference, variantWidth = null) => {
    const nextUrl = URL.createObjectURL(blob);

    if (imageUrlRef.current) {
      URL.revokeObjectURL(imageUrlRef.current);
    }

    imageUrlRef.current = nextUrl;
    loadedReferenceRef.current = reference;
    loadedVariantWidthRef.current = variantWidth;
    setImageUrl(nextUrl);
  }, []);

  const loadImage = useCallback(async ({
    signal,
    reference,
    responsive = true,
    availableVariants = profilePhoto?.variants,
  }) => {
    const variants = responsive && Array.isArray(availableVariants)
      ? availableVariants
      : [];
    const targetWidth = renderedAvatarWidth() * Math.max(1, window.devicePixelRatio || 1);
    const selected = variants.find((variant) => variant.width >= targetWidth) ?? null;

    if (selected !== null) {
      try {
        const blob = await profilePhotoService.download({ signal, width: selected.width });
        replaceImage(blob, reference, selected.width);
        return;
      } catch (error) {
        if (signal?.aborted) throw error;
      }
    }

    const blob = await profilePhotoService.download({ signal });
    replaceImage(blob, reference);
  }, [profilePhoto?.variants, replaceImage]);

  useEffect(() => {
    if (!referenceUrl) {
      masterOnlyReferenceRef.current = null;
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

    loadImage({
      signal: controller.signal,
      reference: referenceUrl,
      responsive: masterOnlyReferenceRef.current !== referenceUrl,
    })
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
      masterOnlyReferenceRef.current = null;

      try {
        await loadImage({
          reference: nextProfilePhoto.url,
          availableVariants: nextProfilePhoto.variants,
        });
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
      masterOnlyReferenceRef.current = null;
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
    masterOnlyReferenceRef.current = null;
    setError(null);
    setReloadToken((current) => current + 1);
  }, []);

  const handleImageFailure = useCallback(() => {
    const failedVariant = loadedVariantWidthRef.current !== null;
    clearImage();
    if (!failedVariant || !referenceUrl) {
      setError('No se pudo mostrar la foto de perfil. Puedes volver a intentarlo.');
      return;
    }

    masterOnlyReferenceRef.current = referenceUrl;
    setError(null);
    setReloadToken((current) => current + 1);
  }, [clearImage, referenceUrl]);

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
