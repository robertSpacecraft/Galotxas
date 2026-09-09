import { normalizeResponsiveVariants } from '../../utils/responsiveImageContract';

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
    || !isStablePrivatePhotoUrl(value.url)
  ) {
    throw new InvalidProfilePhotoResponseError();
  }

  const keys = Object.keys(value);
  const allowedKeys = new Set(['url', 'width', 'height', 'variants']);
  const hasDimensions = Number.isInteger(value.width) && value.width > 0
    && Number.isInteger(value.height) && value.height > 0;
  if (
    keys.some((key) => !allowedKeys.has(key))
    || ((value.width !== undefined || value.height !== undefined) && !hasDimensions)
  ) {
    throw new InvalidProfilePhotoResponseError();
  }

  const variants = normalizeResponsiveVariants({
    variants: value.variants,
    masterUrl: value.url,
    masterPath: PROFILE_PHOTO_PATH,
  });
  const safeVariants = variants !== null
    && variants.every((variant) => [128, 256].includes(variant.width))
    ? variants
    : [];

  return {
    url: value.url,
    ...(hasDimensions ? { width: value.width, height: value.height } : {}),
    ...(safeVariants.length > 0 ? { variants: safeVariants } : {}),
  };
};
