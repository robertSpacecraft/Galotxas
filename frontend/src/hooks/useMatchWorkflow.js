import { useState, useCallback } from 'react';
import { matchesService } from '../api/matches';

export const useMatchWorkflow = (matchId) => {
  const [match, setMatch] = useState(null);
  const [workflow, setWorkflow] = useState(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState(null);

  const fetchWorkflow = useCallback(async () => {
    if (!matchId) {
      setLoading(false);
      return;
    }
    try {
      setLoading(true);
      setError(null);
      const data = await matchesService.getWorkflow(matchId);
      setMatch(data?.match ?? null);
      setWorkflow(data?.workflow ?? null);
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'No se ha podido cargar el flujo del partido.');
    } finally {
      setLoading(false);
    }
  }, [matchId]);

  const submitResult = async (params) => {
    try {
      setActionLoading(true);
      setError(null);
      setMessage(null);
      const response = await matchesService.submitResult(matchId, params);
      setMessage(response?.message ?? 'Resultado enviado correctamente.');
      await fetchWorkflow();
      return true;
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'No se ha podido enviar el resultado.');
      return false;
    } finally {
      setActionLoading(false);
    }
  };

  const confirmResult = async (comment = null) => {
    try {
      setActionLoading(true);
      setError(null);
      setMessage(null);
      const response = await matchesService.confirmResult(matchId, comment);
      setMessage(response?.message ?? 'Resultado confirmado correctamente.');
      await fetchWorkflow();
      return true;
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'No se ha podido confirmar el resultado.');
      return false;
    } finally {
      setActionLoading(false);
    }
  };

  return {
    match,
    workflow,
    loading,
    actionLoading,
    error,
    message,
    fetchWorkflow,
    submitResult,
    confirmResult
  };
};
