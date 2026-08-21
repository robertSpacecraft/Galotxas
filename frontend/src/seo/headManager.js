const managedSelectors = [
  'meta[name="description"]',
  'meta[name="robots"]',
  'link[rel="canonical"]',
  'meta[property="og:type"]',
  'meta[property="og:site_name"]',
  'meta[property="og:title"]',
  'meta[property="og:description"]',
  'meta[property="og:url"]',
  'meta[property="og:image"]',
  'meta[property="article:published_time"]',
  'script[data-public-seo-jsonld]',
];

const removeElements = (selector) => {
  document.head.querySelectorAll(selector).forEach((element) => element.remove());
};

const setSingleElement = (selector, createElement, configure) => {
  const existing = [...document.head.querySelectorAll(selector)];
  const element = existing.shift() ?? createElement();

  existing.forEach((duplicate) => duplicate.remove());
  configure(element);

  if (!element.isConnected) document.head.appendChild(element);
};

const setNamedMeta = (name, content) => setSingleElement(
  `meta[name="${name}"]`,
  () => document.createElement('meta'),
  (element) => {
    element.setAttribute('name', name);
    element.setAttribute('content', content);
  },
);

const setPropertyMeta = (property, content) => setSingleElement(
  `meta[property="${property}"]`,
  () => document.createElement('meta'),
  (element) => {
    element.setAttribute('property', property);
    element.setAttribute('content', content);
  },
);

export const applySeoMetadata = (metadata) => {
  const previousTitle = document.title;
  const snapshots = managedSelectors.flatMap((selector) => (
    [...document.head.querySelectorAll(selector)].map((element) => element.cloneNode(true))
  ));

  document.title = metadata.title;
  setNamedMeta('description', metadata.description);
  setNamedMeta('robots', metadata.robots);

  if (metadata.canonicalUrl) {
    setSingleElement(
      'link[rel="canonical"]',
      () => document.createElement('link'),
      (element) => {
        element.setAttribute('rel', 'canonical');
        element.setAttribute('href', metadata.canonicalUrl);
      },
    );
  } else {
    removeElements('link[rel="canonical"]');
  }

  if (metadata.openGraph) {
    setPropertyMeta('og:type', metadata.openGraph.type);
    setPropertyMeta('og:site_name', metadata.openGraph.siteName);
    setPropertyMeta('og:title', metadata.openGraph.title);
    setPropertyMeta('og:description', metadata.openGraph.description);
    setPropertyMeta('og:url', metadata.openGraph.url);
    if (metadata.openGraph.image) {
      setPropertyMeta('og:image', metadata.openGraph.image);
    } else {
      removeElements('meta[property="og:image"]');
    }
    if (metadata.openGraph.publishedTime) {
      setPropertyMeta('article:published_time', metadata.openGraph.publishedTime);
    } else {
      removeElements('meta[property="article:published_time"]');
    }
  } else {
    for (const property of [
      'og:type',
      'og:site_name',
      'og:title',
      'og:description',
      'og:url',
      'og:image',
      'article:published_time',
    ]) {
      removeElements(`meta[property="${property}"]`);
    }
  }

  if (metadata.jsonLd) {
    setSingleElement(
      'script[data-public-seo-jsonld]',
      () => document.createElement('script'),
      (element) => {
        element.setAttribute('type', 'application/ld+json');
        element.setAttribute('data-public-seo-jsonld', '');
        element.textContent = JSON.stringify(metadata.jsonLd);
      },
    );
  } else {
    removeElements('script[data-public-seo-jsonld]');
  }

  return () => {
    managedSelectors.forEach(removeElements);
    snapshots.forEach((element) => document.head.appendChild(element));
    document.title = previousTitle;
  };
};
