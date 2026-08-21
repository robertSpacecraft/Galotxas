export class InvalidNewsResponseError extends Error {
  constructor() {
    super('Invalid news response');
    this.name = 'InvalidNewsResponseError';
  }
}

const fail = () => {
  throw new InvalidNewsResponseError();
};

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const isPositiveInteger = (value) => Number.isInteger(value) && value > 0;
const isNonNegativeInteger = (value) => Number.isInteger(value) && value >= 0;
const isNonEmptyString = (value) => typeof value === 'string' && value.trim() !== '';
const nullableString = (value) => value === null || typeof value === 'string';

const normalizeImageUrl = (value, slug) => {
  if (!isNonEmptyString(value)) fail();

  let parsed;
  try {
    parsed = new URL(value, window.location.origin);
  } catch {
    fail();
  }

  if (
    !['http:', 'https:'].includes(parsed.protocol)
    || parsed.username !== ''
    || parsed.password !== ''
    || parsed.search !== ''
    || parsed.hash !== ''
    || parsed.pathname !== `/api/v1/news/${slug}/image`
    || /x-amz-/i.test(value)
  ) {
    fail();
  }

  return value;
};

const normalizePublishedAt = (value) => {
  if (!isNonEmptyString(value) || Number.isNaN(Date.parse(value))) fail();

  return value;
};

const normalizeSummary = (article) => {
  if (
    !isObject(article)
    || !isNonEmptyString(article.slug)
    || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(article.slug)
    || !isNonEmptyString(article.title)
    || !isNonEmptyString(article.excerpt)
    || !isObject(article.image)
    || !isPositiveInteger(article.image.width)
    || !isPositiveInteger(article.image.height)
    || !isNonEmptyString(article.image.alt)
    || !nullableString(article.image.credit)
  ) {
    fail();
  }

  return {
    slug: article.slug,
    title: article.title,
    excerpt: article.excerpt,
    published_at: normalizePublishedAt(article.published_at),
    image: {
      url: normalizeImageUrl(article.image.url, article.slug),
      width: article.image.width,
      height: article.image.height,
      alt: article.image.alt,
      credit: article.image.credit,
    },
  };
};

const normalizeMeta = (meta) => {
  if (
    !isObject(meta)
    || !isPositiveInteger(meta.current_page)
    || !isPositiveInteger(meta.last_page)
    || meta.per_page !== 12
    || !isNonNegativeInteger(meta.total)
    || typeof meta.has_more !== 'boolean'
  ) {
    fail();
  }

  return {
    current_page: meta.current_page,
    last_page: meta.last_page,
    per_page: meta.per_page,
    total: meta.total,
    has_more: meta.has_more,
  };
};

export const normalizeNewsListResponse = (payload) => {
  if (!isObject(payload) || !Array.isArray(payload.data)) fail();

  return {
    articles: payload.data.map(normalizeSummary),
    meta: normalizeMeta(payload.meta),
  };
};

export const normalizeNewsArticleResponse = (payload) => {
  if (!isObject(payload) || !isObject(payload.data)) fail();

  const summary = normalizeSummary(payload.data);
  if (
    !isNonEmptyString(payload.data.body)
    || payload.data.body.length > 20000
    || !nullableString(payload.data.seo_title)
    || !nullableString(payload.data.seo_description)
  ) {
    fail();
  }

  return {
    ...summary,
    body: payload.data.body,
    seo_title: payload.data.seo_title,
    seo_description: payload.data.seo_description,
  };
};
