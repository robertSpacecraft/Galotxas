import { clubPages } from '../club/clubRoutes';

export class InvalidCmsNavigationResponseError extends Error {
  constructor() {
    super('Invalid CMS navigation response');
    this.name = 'InvalidCmsNavigationResponseError';
  }
}

const allowedItemKeys = Object.freeze(['label', 'slot', 'sort_order', 'url']);
const safeCmsUrlPattern = /^\/contenidos\/[a-z0-9]+(?:-[a-z0-9]+)*$/;
const reservedSlugs = new Set(Object.values(clubPages).map(({ slug }) => slug));

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const hasControlCharacter = (value) => Array.from(value).some((character) => {
  const codePoint = character.codePointAt(0);

  return codePoint <= 31 || codePoint === 127;
});

const hasExactItemShape = (item) => {
  const keys = Object.keys(item).sort();

  return keys.length === allowedItemKeys.length
    && keys.every((key, index) => key === allowedItemKeys[index]);
};

const normalizeLabel = (value) => {
  if (typeof value !== 'string') return null;

  const label = value.trim();
  if (
    label === ''
    || label.length > 80
    || hasControlCharacter(label)
    || /[<>]/u.test(label)
    || /^(?:[a-z][a-z0-9+.-]*:|\/\/|\/)/iu.test(label)
  ) {
    return null;
  }

  return label;
};

const normalizeUrl = (value) => {
  if (typeof value !== 'string' || !safeCmsUrlPattern.test(value)) return null;

  const slug = value.slice('/contenidos/'.length);

  return reservedSlugs.has(slug) ? null : value;
};

const normalizeItem = (item, index) => {
  if (!isObject(item) || !hasExactItemShape(item)) return null;

  const label = normalizeLabel(item.label);
  const url = normalizeUrl(item.url);
  if (
    item.slot !== 'club'
    || label === null
    || url === null
    || !Number.isInteger(item.sort_order)
    || item.sort_order < 0
  ) {
    return null;
  }

  return {
    slot: 'club',
    label,
    url,
    sort_order: item.sort_order,
    sourceIndex: index,
  };
};

export const normalizeCmsNavigationItems = (items) => {
  if (!Array.isArray(items)) return [];

  const seenUrls = new Set();

  return items
    .map(normalizeItem)
    .filter((item) => {
      if (item === null || seenUrls.has(item.url)) return false;

      seenUrls.add(item.url);
      return true;
    })
    .sort((left, right) => left.sort_order - right.sort_order || left.sourceIndex - right.sourceIndex)
    .map(({ slot, label, url, sort_order: sortOrder }) => ({
      slot,
      label,
      url,
      sort_order: sortOrder,
    }));
};

export const normalizeCmsNavigationResponse = (payload) => {
  if (!isObject(payload)) throw new InvalidCmsNavigationResponseError();

  const keys = Object.keys(payload).sort();
  const hasClosedRootShape = (
    (keys.length === 1 && keys[0] === 'data')
    || (keys.length === 2 && keys[0] === 'data' && keys[1] === 'message' && payload.message === null)
  );

  if (!hasClosedRootShape || !Array.isArray(payload.data)) {
    throw new InvalidCmsNavigationResponseError();
  }

  return normalizeCmsNavigationItems(payload.data);
};
