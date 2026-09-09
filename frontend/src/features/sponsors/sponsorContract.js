import { normalizeResponsiveVariants } from '../../utils/responsiveImageContract';

export class InvalidSponsorsResponseError extends Error {
  constructor() {
    super('Invalid sponsors response');
    this.name = 'InvalidSponsorsResponseError';
  }
}

const fail = () => {
  throw new InvalidSponsorsResponseError();
};

const isAbsoluteHttpUrl = (value) => {
  if (typeof value !== 'string') return false;

  try {
    const parsed = new URL(value);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
};

const isExternalHttpsUrl = (value) => {
  if (typeof value !== 'string') return false;

  try {
    const parsed = new URL(value);
    return parsed.protocol === 'https:'
      && parsed.hostname !== ''
      && parsed.username === ''
      && parsed.password === '';
  } catch {
    return false;
  }
};

const positiveInteger = (value) => Number.isInteger(value) && value > 0;

const normalizeSponsor = (sponsor) => {
  if (
    !sponsor
    || typeof sponsor !== 'object'
    || !positiveInteger(sponsor.id)
    || typeof sponsor.name !== 'string'
    || sponsor.name.trim() === ''
    || !sponsor.logo
    || typeof sponsor.logo !== 'object'
    || !isAbsoluteHttpUrl(sponsor.logo.url)
    || !positiveInteger(sponsor.logo.width)
    || !positiveInteger(sponsor.logo.height)
    || (sponsor.website_url !== null && !isExternalHttpsUrl(sponsor.website_url))
  ) {
    fail();
  }

  const masterPath = `/api/v1/sponsors/${sponsor.id}/logo`;
  const variants = normalizeResponsiveVariants({
    variants: sponsor.logo.variants,
    masterUrl: sponsor.logo.url,
    masterPath,
  });
  const safeVariants = variants ?? [];

  return {
    id: sponsor.id,
    name: sponsor.name.trim(),
    logo: {
      url: sponsor.logo.url,
      width: sponsor.logo.width,
      height: sponsor.logo.height,
      ...(safeVariants.length > 0 ? { variants: safeVariants } : {}),
    },
    website_url: sponsor.website_url,
  };
};

export const normalizeSponsors = (data) => {
  if (!Array.isArray(data)) fail();

  return data.map(normalizeSponsor);
};
