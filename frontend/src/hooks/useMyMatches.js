import { useState, useEffect } from 'react';
import api from '../api/api';

export const useMyMatches = () => {
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchMatches = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.get('/me/matches');
      // The API returns { data: {...}, message: "..." }
      if (response.data && response.data.data) {
        setMatches(response.data.data);
      } else {
        setMatches(response.data || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error fetching matches');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMatches();
  }, []);

  return { matches, loading, error, refetch: fetchMatches };
};
