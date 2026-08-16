import api from '../../api/client';
import { normalizeSponsors } from './sponsorContract';

export const sponsorService = {
  getAll: async ({ signal } = {}) => {
    const response = await api.get('/sponsors', { signal });

    return normalizeSponsors(response.data?.data);
  },
};
