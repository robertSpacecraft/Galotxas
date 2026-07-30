import { useCallback, useEffect, useRef, useState } from 'react';
import { schoolService } from './schoolService';

const LOAD_ERROR_MESSAGE = 'No se ha podido cargar la información de la Escuela.';

const initialState = {
  data: null,
  status: 'loading',
  error: null,
};

export const useSchoolOverview = () => {
  const [state, setState] = useState(initialState);
  const activeRequest = useRef(0);

  const completeRequest = useCallback((requestId, data) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    setState({
      data,
      status: data === null ? 'empty' : 'content',
      error: null,
    });
  }, []);

  const failRequest = useCallback((requestId) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    setState({
      data: null,
      status: 'error',
      error: LOAD_ERROR_MESSAGE,
    });
  }, []);

  const load = useCallback(async () => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;
    setState(initialState);

    try {
      const data = await schoolService.getOverview();
      completeRequest(requestId, data);

      return { ok: true, data };
    } catch {
      failRequest(requestId);

      return { ok: false, data: null };
    }
  }, [completeRequest, failRequest]);

  useEffect(() => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;

    schoolService.getOverview().then(
      (data) => completeRequest(requestId, data),
      () => failRequest(requestId),
    );

    return () => {
      activeRequest.current += 1;
    };
  }, [completeRequest, failRequest]);

  return {
    ...state,
    reload: load,
  };
};
