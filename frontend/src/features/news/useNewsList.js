import { useCallback, useEffect, useRef, useState } from 'react';
import { InvalidNewsResponseError } from './newsContract';
import { newsService } from './newsService';

const initialState = {
  articles: [],
  meta: null,
  status: 'loading',
  loadMoreStatus: 'idle',
  error: null,
  loadMoreError: null,
};

const errorStatus = (error) => (
  error instanceof InvalidNewsResponseError ? 'invalid' : 'error'
);

export const useNewsList = () => {
  const [state, setState] = useState(initialState);
  const activeRequest = useRef({ id: 0, controller: null });

  const loadPage = useCallback(async (page, append = false) => {
    activeRequest.current.controller?.abort();
    const controller = new AbortController();
    const requestId = activeRequest.current.id + 1;
    activeRequest.current = { id: requestId, controller };

    setState((current) => (append ? {
      ...current,
      loadMoreStatus: 'loading',
      loadMoreError: null,
    } : initialState));

    try {
      const result = await newsService.getList({ page, signal: controller.signal });
      if (activeRequest.current.id !== requestId) return false;

      setState((current) => {
        const articles = append
          ? [...new Map(
              [...current.articles, ...result.articles].map((article) => [article.slug, article]),
            ).values()]
          : result.articles;

        return {
          articles,
          meta: result.meta,
          status: articles.length === 0 ? 'empty' : 'content',
          loadMoreStatus: 'idle',
          error: null,
          loadMoreError: null,
        };
      });

      return true;
    } catch (error) {
      if (activeRequest.current.id !== requestId || controller.signal.aborted) return false;

      setState((current) => (append ? {
        ...current,
        loadMoreStatus: 'error',
        loadMoreError: 'No se han podido cargar más noticias.',
      } : {
        ...initialState,
        status: errorStatus(error),
        error: error instanceof InvalidNewsResponseError
          ? 'La respuesta de Noticias no tiene un formato válido.'
          : 'No se han podido cargar las noticias.',
      }));

      return false;
    }
  }, []);

  useEffect(() => {
    loadPage(1);

    return () => {
      activeRequest.current.id += 1;
      activeRequest.current.controller?.abort();
    };
  }, [loadPage]);

  const loadMore = useCallback(() => {
    if (
      state.status !== 'content'
      || !state.meta?.has_more
      || state.loadMoreStatus === 'loading'
    ) {
      return Promise.resolve(false);
    }

    return loadPage(state.meta.current_page + 1, true);
  }, [loadPage, state.loadMoreStatus, state.meta, state.status]);

  return {
    ...state,
    reload: () => loadPage(1),
    loadMore,
  };
};
