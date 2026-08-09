import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { normalizePublicPathname, resolveSeoRoute } from './seoManifest';

export const RouteAccessibility = () => {
  const location = useLocation();
  const pathname = normalizePublicPathname(location.pathname);
  const previousPathname = useRef(pathname);
  const [announcement, setAnnouncement] = useState('');

  useEffect(() => {
    if (previousPathname.current === pathname) return;

    previousPathname.current = pathname;
    const route = resolveSeoRoute(pathname);
    let isActive = true;

    queueMicrotask(() => {
      if (!isActive) return;

      document.getElementById('main-content')?.focus({ preventScroll: true });
      setAnnouncement(route.title);
    });

    return () => {
      isActive = false;
    };
  }, [pathname]);

  return (
    <div className="route-announcer" aria-live="polite" aria-atomic="true">
      {announcement}
    </div>
  );
};
