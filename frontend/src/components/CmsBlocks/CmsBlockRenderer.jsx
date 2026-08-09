import styles from './CmsBlockRenderer.module.css';

const isExternalUrl = (url = '') => /^https?:\/\//i.test(url);
const isInternalPath = (url = '') => url.startsWith('/') && !url.startsWith('//');
const isMailtoUrl = (url = '') => /^mailto:[^@\s/?#]+@[^@\s/?#]+(?:\.[^@\s/?#]+)+$/.test(url);
const isAllowedMediaUrl = (url = '') => isExternalUrl(url) || isInternalPath(url);
const isAllowedLinkUrl = (url = '') => isAllowedMediaUrl(url) || isMailtoUrl(url);

const linkPropsFor = (url = '') => {
  if (!isExternalUrl(url)) {
    return {};
  }

  return {
    target: '_blank',
    rel: 'noreferrer',
  };
};

const clampHeadingLevel = (level) => {
  const parsedLevel = Number(level);

  if (!Number.isInteger(parsedLevel)) {
    return 2;
  }

  // La página ya aporta su H1; los bloques CMS sólo completan su jerarquía interna.
  return Math.min(Math.max(parsedLevel, 2), 6);
};

const renderHeading = (data) => {
  const level = clampHeadingLevel(data?.level);
  const HeadingTag = `h${level}`;

  return <HeadingTag className={styles.heading}>{data?.text}</HeadingTag>;
};

const renderText = (data) => (
  <p className={styles.text}>{data?.text}</p>
);

const renderList = (data) => {
  const items = Array.isArray(data?.items) ? data.items : [];

  if (items.length === 0) {
    return null;
  }

  return (
    <ul className={styles.list}>
      {items.map((item, index) => (
        <li key={`${item}-${index}`}>{item}</li>
      ))}
    </ul>
  );
};

const renderImage = (data) => {
  if (!data?.url || !isAllowedMediaUrl(data.url)) {
    return null;
  }

  return (
    <figure className={styles.imageBlock}>
      <img src={data.url} alt={data.alt || ''} className={styles.image} />
      {data.alt ? <figcaption>{data.alt}</figcaption> : null}
    </figure>
  );
};

const renderGallery = (data) => {
  const urls = Array.isArray(data?.urls) ? data.urls.filter(isAllowedMediaUrl) : [];

  if (urls.length === 0) {
    return null;
  }

  return (
    <div className={styles.gallery}>
      {urls.map((url, index) => (
        <img
          key={`${url}-${index}`}
          src={url}
          alt=""
          className={styles.galleryImage}
        />
      ))}
    </div>
  );
};

const renderButton = (data) => {
  if (!data?.label || !data?.url || !isAllowedLinkUrl(data.url)) {
    return null;
  }

  return (
    <a href={data.url} className={styles.button} {...linkPropsFor(data.url)}>
      {data.label}
    </a>
  );
};

const renderDocumentLink = (data) => {
  if (!data?.label || !data?.url || !isAllowedLinkUrl(data.url)) {
    return null;
  }

  return (
    <a href={data.url} className={styles.documentLink} {...linkPropsFor(data.url)}>
      {data.label}
    </a>
  );
};

export const CmsBlockRenderer = ({ block }) => {
  const data = block?.data || {};

  const renderedBlock = {
    heading: () => renderHeading(data),
    text: () => renderText(data),
    list: () => renderList(data),
    image: () => renderImage(data),
    gallery: () => renderGallery(data),
    button: () => renderButton(data),
    document_link: () => renderDocumentLink(data),
  }[block?.type]?.();

  if (!renderedBlock) {
    return null;
  }

  return (
    <section className={styles.block} data-block-type={block.type}>
      {renderedBlock}
    </section>
  );
};
