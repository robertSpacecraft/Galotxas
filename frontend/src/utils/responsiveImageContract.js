const ALLOWED_MIME_TYPES = new Set(['image/png', 'image/webp']);

const positiveInteger = (value) => Number.isInteger(value) && value > 0;

export const normalizeResponsiveVariants = ({ variants, masterUrl, masterPath }) => {
  if (variants === undefined) return [];
  if (!Array.isArray(variants) || variants.length === 0 || variants.length > 5) return null;

  let master;
  try {
    master = new URL(masterUrl, window.location.origin);
  } catch {
    return null;
  }

  let previousWidth = 0;
  const normalized = [];
  for (const variant of variants) {
    if (
      !variant
      || typeof variant !== 'object'
      || Array.isArray(variant)
      || Object.keys(variant).length !== 4
      || !positiveInteger(variant.width)
      || variant.width <= previousWidth
      || !positiveInteger(variant.height)
      || !ALLOWED_MIME_TYPES.has(variant.mime_type)
    ) {
      return null;
    }

    let url;
    try {
      url = new URL(variant.url, window.location.origin);
    } catch {
      return null;
    }
    if (
      !['http:', 'https:'].includes(url.protocol)
      || url.origin !== master.origin
      || url.username !== ''
      || url.password !== ''
      || url.search !== ''
      || url.hash !== ''
      || url.pathname !== `${masterPath}/${variant.width}`
    ) {
      return null;
    }

    normalized.push({
      url: variant.url,
      width: variant.width,
      height: variant.height,
      mime_type: variant.mime_type,
    });
    previousWidth = variant.width;
  }

  return normalized;
};
