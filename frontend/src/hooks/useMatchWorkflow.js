import { useState, useCallback } from 'react';
import api from '../api/api';

export const useMatchWorkflow = (matchId) => {
  const [workflow, setWorkflow] = useState(null);
  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchWorkflow = useCallback(async () => {
    if (!matchId) return;
    try {
      setLoading(true);
      setError(null);
      const res = await api.get(`/matches/${matchId}/workflow`);
      setWorkflow(res.data?.data || res.data);
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error fetching workflow');
    } finally {
      setLoading(false);
    }
  }, [matchId]);

  const submitResult = async (params) => {
    try {
      setActionLoading(true);
      setError(null);
      await api.post(`/matches/${matchId}/submit-result`, params);
      await fetchWorkflow(); // Reload the workflow state
      return true;
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error submitting result');
      return false;
    } finally {
      setActionLoading(false);
    }
  };

  const confirmResult = async () => {
    try {
      setActionLoading(true);
      setError(null);
      await api.post(`/matches/${matchId}/confirm-result`);
      await fetchWorkflow(); // Reload the workflow state
      return true;
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error confirming result');
      return false;
    } finally {
      setActionLoading(false);
    }
  };

  return {
    workflow,
    loading,
    actionLoading,
    error,
    fetchWorkflow,
    submitResult,
    confirmResult
  };
};
