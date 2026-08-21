import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useSeoOverride } from '../../seo/SeoOverrideContext';

export const PageMetadata = ({
  title,
  description,
  classification,
  canonicalPath,
  article,
}) => {
  const location = useLocation();
  const { registerOverride } = useSeoOverride();
  const articleHeadline = article?.headline;
  const articlePublishedAt = article?.publishedAt;
  const articleImage = article?.image;

  useEffect(() => registerOverride(location.pathname, {
    title,
    description,
    ...(classification ? { classification } : {}),
    ...(canonicalPath !== undefined ? { canonicalPath } : {}),
    ...(articleHeadline ? {
      article: {
        headline: articleHeadline,
        publishedAt: articlePublishedAt,
        image: articleImage,
      },
    } : {}),
  }), [
    articleHeadline,
    articleImage,
    articlePublishedAt,
    canonicalPath,
    classification,
    description,
    location.pathname,
    registerOverride,
    title,
  ]);

  return null;
};
