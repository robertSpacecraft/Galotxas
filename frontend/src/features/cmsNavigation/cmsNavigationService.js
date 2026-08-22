import api from '../../api/client';
import { normalizeCmsNavigationResponse } from './cmsNavigationContract';

export const cmsNavigationService = {
  getAll: async ({ signal } = {}) => {
    const response = await api.get('/cms-navigation', { signal });

    return normalizeCmsNavigationResponse(response.data);
  },
};
