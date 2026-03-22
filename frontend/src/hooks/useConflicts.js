import { useState, useCallback, useEffect } from 'react';
import api from '../api/api';

export const useConflicts = () => {
  const [conflicts, setConflicts] = useState([]);
  const [selectedConflictData, setSelectedConflictData] = useState(null);
  const [loadingList, setLoadingList] = useState(false);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchConflicts = useCallback(async () => {
    try {
      setLoadingList(true);
      setError(null);
      const res = await api.get('/admin/matches/under-review');
      setConflicts(res.data?.data || res.data || []);
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error fetching conflicts');
    } finally {
      setLoadingList(false);
    }
  }, []);

  const fetchConflictDetail = async (matchId) => {
    try {
      setLoadingDetail(true);
      setError(null);
      const res = await api.get(`/admin/matches/${matchId}/conflict`);
      setSelectedConflictData({
        matchId,
        details: res.data?.data || res.data
      });
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error fetching conflict details');
    } finally {
      setLoadingDetail(false);
    }
  };

  const resolveConflict = async (matchId, params) => {
    try {
      setActionLoading(true);
      setError(null);
      await api.post(`/admin/matches/${matchId}/resolve-conflict`, params);
      // Clear selection and refresh list after resolution
      setSelectedConflictData(null);
      await fetchConflicts();
      return true;
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error resolving conflict');
      return false;
    } finally {
      setActionLoading(false);
    }
  };

  useEffect(() => {
    fetchConflicts();
  }, [fetchConflicts]);

  return {
    conflicts,
    selectedConflictData,
    loadingList,
    loadingDetail,
    actionLoading,
    error,
    fetchConflicts,
    fetchConflictDetail,
    resolveConflict,
    clearSelection: () => setSelectedConflictData(null)
  };
};
