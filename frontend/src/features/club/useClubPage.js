import { useCallback, useEffect, useRef, useState } from 'react';
import { cmsService } from '../../api/cms';

const initialState = {
  page: null,
  status: 'loading',
  error: null,
};

export class InvalidClubPageError extends Error {
  constructor() {
    super('La respuesta de contenido no es válida para esta página.');
    this.name = 'InvalidClubPageError';
  }
}

export const normalizeClubPage = (page, config) => {
  if (
    !page
    || typeof page !== 'object'
    || page.slug !== config.slug
    || typeof page.title !== 'string'
    || !page.title.trim()
  ) {
    throw new InvalidClubPageError();
  }

  return {
    ...page,
    title: page.title.trim(),
    seo_title: typeof page.seo_title === 'string' ? page.seo_title.trim() : '',
    seo_description: typeof page.seo_description === 'string'
      ? page.seo_description.trim()
      : '',
    blocks: Array.isArray(page.blocks) ? page.blocks : [],
  };
};

export const useClubPage = (config) => {
  const [state, setState] = useState(initialState);
  const activeRequest = useRef(0);

  const complete = useCallback((requestId, page) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    setState({ page, status: 'content', error: null });
  }, []);

  const fail = useCallback((requestId, error) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    setState({
      page: null,
      status: error?.status === 404 ? 'notFound' : 'error',
      error: error?.status === 404
        ? null
        : 'No se ha podido cargar el contenido. Inténtalo de nuevo.',
    });
  }, []);

  const load = useCallback(async () => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;
    setState(initialState);

    try {
      const response = await cmsService.getPageBySlug(config.slug);
      const page = normalizeClubPage(response, config);
      complete(requestId, page);
    } catch (error) {
      fail(requestId, error);
    }
  }, [complete, config, fail]);

  useEffect(() => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;

    cmsService.getPageBySlug(config.slug).then(
      (response) => {
        try {
          complete(requestId, normalizeClubPage(response, config));
        } catch (error) {
          fail(requestId, error);
        }
      },
      (error) => fail(requestId, error),
    );

    return () => {
      activeRequest.current += 1;
    };
  }, [complete, config, fail]);

  return {
    ...state,
    reload: load,
  };
};
