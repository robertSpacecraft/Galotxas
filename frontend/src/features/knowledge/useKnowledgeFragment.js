import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

const decodeFragment = (hash) => {
  try {
    return decodeURIComponent(hash.slice(1));
  } catch {
    return null;
  }
};

export const useKnowledgeFragment = (
  contentKey,
  targetSelector = 'h2, h3, h4, h5, h6',
) => {
  const location = useLocation();
  const shouldFocus = location.state?.focusKnowledgeFragment === true;

  useEffect(() => {
    if (!contentKey || !location.hash) {
      return undefined;
    }

    const fragment = decodeFragment(location.hash);
    if (!fragment) {
      return undefined;
    }

    const scrollToFragment = () => {
      const target = globalThis.document.getElementById(fragment);
      if (!target?.matches(targetSelector)) {
        return;
      }

      target.scrollIntoView?.({ block: 'start' });
      if (shouldFocus) {
        target.focus({ preventScroll: true });
      }
    };

    if (typeof globalThis.requestAnimationFrame === 'function') {
      const frame = globalThis.requestAnimationFrame(scrollToFragment);
      return () => globalThis.cancelAnimationFrame(frame);
    }

    const timeout = globalThis.setTimeout(scrollToFragment, 0);
    return () => globalThis.clearTimeout(timeout);
  }, [contentKey, location.hash, location.key, shouldFocus, targetSelector]);
};
