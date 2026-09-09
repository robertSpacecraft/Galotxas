import api from '../../api/client';
import { InvalidProfilePhotoResponseError, normalizeProfilePhoto } from './profilePhotoContract';

const ALLOWED_IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);

const mutationPhoto = (response) => normalizeProfilePhoto(response.data?.data?.profile_photo);

export const profilePhotoService = {
  upload: async (photo) => {
    const formData = new FormData();
    formData.append('photo', photo);
    const response = await api.post('/me/profile-photo', formData, {
      headers: { 'Content-Type': null },
    });

    return mutationPhoto(response);
  },

  remove: async () => {
    const response = await api.delete('/me/profile-photo');

    return mutationPhoto(response);
  },

  download: async ({ signal, width } = {}) => {
    if (width !== undefined && ![128, 256].includes(width)) {
      throw new InvalidProfilePhotoResponseError();
    }
    const path = width === undefined
      ? '/me/profile-photo/image'
      : `/me/profile-photo/image/${width}`;
    const response = await api.get(path, {
      responseType: 'blob',
      signal,
    });
    const blob = response.data;

    if (!(blob instanceof Blob) || !ALLOWED_IMAGE_TYPES.has(blob.type)) {
      throw new InvalidProfilePhotoResponseError();
    }

    return blob;
  },
};
