import { useState, useEffect } from 'react';
import api from '../api/api';

export const useStandings = (categoryId) => {
  const [standings, setStandings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchStandings = async () => {
      if (!categoryId) return;
      try {
        setLoading(true);
        setError(null);
        const res = await api.get(`/categories/${categoryId}/standings`);
        setStandings(res.data?.data || res.data || []);
      } catch (err) {
        setError(err.response?.data?.message || err.message || 'Error fetching standings');
      } finally {
        setLoading(false);
      }
    };
    fetchStandings();
  }, [categoryId]);

  return { standings, loading, error };
};
