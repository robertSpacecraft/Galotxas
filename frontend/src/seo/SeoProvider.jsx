import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { createPublicSiteConfig } from './seoConfig';
import { applySeoMetadata } from './headManager';
import { normalizePublicPathname, resolveSeoRoute } from './seoManifest';
import { createSeoMetadata } from './seoMetadata';
import { SeoOverrideContext } from './SeoOverrideContext';

const runtimeConfig = createPublicSiteConfig(import.meta.env);

export const SeoProvider = ({ children, config = runtimeConfig }) => {
  const location = useLocation();
  const pathname = normalizePublicPathname(location.pathname);
  const [registeredOverride, setRegisteredOverride] = useState(null);
  const activeOverride = registeredOverride?.pathname === pathname
    ? registeredOverride.metadata
    : null;
  const route = useMemo(() => resolveSeoRoute(pathname), [pathname]);
  const metadata = useMemo(() => createSeoMetadata({
    route,
    pathname,
    config,
    override: activeOverride,
  }), [activeOverride, config, pathname, route]);

  useEffect(() => applySeoMetadata(metadata), [metadata]);

  const registerOverride = useCallback((overridePathname, overrideMetadata) => {
    const registration = {
      pathname: normalizePublicPathname(overridePathname),
      metadata: overrideMetadata,
    };

    setRegisteredOverride(registration);

    return () => {
      setRegisteredOverride((current) => (
        current === registration ? null : current
      ));
    };
  }, []);

  const contextValue = useMemo(() => ({ registerOverride }), [registerOverride]);

  return (
    <SeoOverrideContext.Provider value={contextValue}>
      {children}
    </SeoOverrideContext.Provider>
  );
};
