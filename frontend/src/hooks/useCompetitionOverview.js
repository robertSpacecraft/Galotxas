import { useCallback, useEffect, useRef, useState } from 'react';
import { championshipsService } from '../api/championships';

const LOAD_ERROR_MESSAGE = 'No se han podido cargar las temporadas y campeonatos.';

const initialState = {
  data: [],
  status: 'loading',
  error: null,
};

export const useCompetitionOverview = () => {
  const [state, setState] = useState(initialState);
  const activeRequest = useRef(0);

  const completeRequest = useCallback((requestId, response) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    const data = Array.isArray(response) ? response : [];
    setState({
      data,
      status: data.length > 0 ? 'content' : 'empty',
      error: null,
    });
  }, []);

  const failRequest = useCallback((requestId) => {
    if (activeRequest.current !== requestId) {
      return;
    }

    setState({
      data: [],
      status: 'error',
      error: LOAD_ERROR_MESSAGE,
    });
  }, []);

  const reload = useCallback(() => {
    setState(initialState);
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;

    return championshipsService.getSeasons().then(
      (response) => completeRequest(requestId, response),
      () => failRequest(requestId),
    );
  }, [completeRequest, failRequest]);

  useEffect(() => {
    const requestId = activeRequest.current + 1;
    activeRequest.current = requestId;
    championshipsService.getSeasons().then(
      (response) => completeRequest(requestId, response),
      () => failRequest(requestId),
    );

    return () => {
      activeRequest.current += 1;
    };
  }, [completeRequest, failRequest]);

  return {
    ...state,
    loading: state.status === 'loading',
    reload,
  };
};
