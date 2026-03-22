import { useState, useEffect } from 'react';
import api from '../api/api';

export const useSchedule = (categoryId) => {
  const [schedule, setSchedule] = round => useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchSchedule = async () => {
      if (!categoryId) return;
      try {
        setLoading(true);
        setError(null);
        const res = await api.get(`/categories/${categoryId}/schedule`);
        const data = res.data?.data || res.data || [];
        
        // Ensure data is array. Sometimes APIs return grouped objects.
        if (Array.isArray(data)) {
          setSchedule(data);
        } else {
          // If it's an object containing rounds/matchdays
          setSchedule(Object.values(data).flat() || []);
        }
      } catch (err) {
        setError(err.response?.data?.message || err.message || 'Error fetching schedule');
      } finally {
        setLoading(false);
      }
    };
    fetchSchedule();
  }, [categoryId]);

  return { schedule, loading, error };
};
