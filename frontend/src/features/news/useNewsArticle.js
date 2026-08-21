import { useCallback, useEffect, useState } from 'react';
import { InvalidNewsResponseError } from './newsContract';
import { newsService } from './newsService';

const initialState = {
  article: null,
  slug: null,
  status: 'loading',
  error: null,
};

const resolveFailure = (error, slug) => {
  if (error?.response?.status === 404) {
    return { ...initialState, slug, status: 'not-found' };
  }

  if (error instanceof InvalidNewsResponseError) {
    return {
      ...initialState,
      slug,
      status: 'invalid',
      error: 'La respuesta de la noticia no tiene un formato válido.',
    };
  }

  return {
    ...initialState,
    slug,
    status: 'error',
    error: 'No se ha podido cargar la noticia.',
  };
};

export const useNewsArticle = (slug) => {
  const [state, setState] = useState(initialState);
  const [requestVersion, setRequestVersion] = useState(0);

  const reload = useCallback(() => {
    setState(initialState);
    setRequestVersion((version) => version + 1);
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;

    newsService.getBySlug(slug, { signal: controller.signal }).then(
      (article) => {
        if (active) setState({ article, slug, status: 'content', error: null });
      },
      (error) => {
        if (active && !controller.signal.aborted) setState(resolveFailure(error, slug));
      },
    );

    return () => {
      active = false;
      controller.abort();
    };
  }, [requestVersion, slug]);

  const currentState = state.slug === slug ? state : initialState;

  return { ...currentState, reload };
};
