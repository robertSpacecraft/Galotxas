export class InvalidProfilePhotoResponseError extends Error {
  constructor() {
    super('Invalid profile photo response');
    this.name = 'InvalidProfilePhotoResponseError';
  }
}

const PROFILE_PHOTO_PATH = '/api/v1/me/profile-photo/image';

const isStablePrivatePhotoUrl = (value) => {
  if (typeof value !== 'string') return false;

  try {
    const url = new URL(value);

    return (url.protocol === 'http:' || url.protocol === 'https:')
      && url.pathname === PROFILE_PHOTO_PATH
      && url.username === ''
      && url.password === ''
      && url.search === ''
      && url.hash === '';
  } catch {
    return false;
  }
};

export const normalizeProfilePhoto = (value) => {
  if (value === null) return null;

  if (
    !value
    || typeof value !== 'object'
    || Array.isArray(value)
    || Object.keys(value).length !== 1
    || !isStablePrivatePhotoUrl(value.url)
  ) {
    throw new InvalidProfilePhotoResponseError();
  }

  return { url: value.url };
};
