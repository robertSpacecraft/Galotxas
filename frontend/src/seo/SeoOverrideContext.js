import { createContext, useContext } from 'react';

export const SeoOverrideContext = createContext(null);

export const useSeoOverride = () => {
  const context = useContext(SeoOverrideContext);

  if (!context) {
    throw new Error('PageMetadata debe renderizarse dentro de SeoProvider.');
  }

  return context;
};
