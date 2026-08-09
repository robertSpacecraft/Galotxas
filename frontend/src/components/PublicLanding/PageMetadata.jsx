import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useSeoOverride } from '../../seo/SeoOverrideContext';

export const PageMetadata = ({
  title,
  description,
  classification,
  canonicalPath,
}) => {
  const location = useLocation();
  const { registerOverride } = useSeoOverride();

  useEffect(() => registerOverride(location.pathname, {
    title,
    description,
    ...(classification ? { classification } : {}),
    ...(canonicalPath !== undefined ? { canonicalPath } : {}),
  }), [
    canonicalPath,
    classification,
    description,
    location.pathname,
    registerOverride,
    title,
  ]);

  return null;
};
