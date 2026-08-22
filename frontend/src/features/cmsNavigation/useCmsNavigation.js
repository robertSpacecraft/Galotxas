import { useEffect, useState } from 'react';
import { cmsNavigationService } from './cmsNavigationService';

export const useCmsNavigation = () => {
  const [items, setItems] = useState([]);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;

    cmsNavigationService.getAll({ signal: controller.signal }).then(
      (nextItems) => {
        if (active) setItems(nextItems);
      },
      () => {
        if (active) setItems([]);
      },
    );

    return () => {
      active = false;
      controller.abort();
    };
  }, []);

  return items;
};
