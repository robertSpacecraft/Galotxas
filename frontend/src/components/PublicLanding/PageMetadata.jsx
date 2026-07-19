import { useEffect } from 'react';

const setMetaContent = (name, content) => {
  let element = document.head.querySelector(`meta[name="${name}"]`);
  const created = !element;

  if (!element) {
    element = document.createElement('meta');
    element.setAttribute('name', name);
    document.head.appendChild(element);
  }

  const hadContent = element.hasAttribute('content');
  const previousContent = element.getAttribute('content');
  element.setAttribute('content', content);

  return () => {
    if (created) {
      element.remove();
      return;
    }

    if (hadContent) {
      element.setAttribute('content', previousContent);
    } else {
      element.removeAttribute('content');
    }
  };
};

export const PageMetadata = ({ title, description, robots = null }) => {
  useEffect(() => {
    const previousTitle = document.title;
    const restoreDescription = setMetaContent('description', description);
    const restoreRobots = robots ? setMetaContent('robots', robots) : null;

    document.title = title;

    return () => {
      document.title = previousTitle;
      restoreDescription();
      restoreRobots?.();
    };
  }, [description, robots, title]);

  return null;
};
