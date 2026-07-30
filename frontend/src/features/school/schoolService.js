import api from '../../api/client';
import { normalizeSchoolOverview } from './schoolContract';

export const schoolService = {
  getOverview: async ({ signal } = {}) => {
    const response = await api.get('/school', { signal });

    return normalizeSchoolOverview(response.data?.data ?? null);
  },

  createEnrollment: async (payload, { signal } = {}) => {
    const response = await api.post('/school/enrollments', payload, { signal });

    return response.data;
  },
};
