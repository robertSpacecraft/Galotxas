import api from './api';

const payload = (response) => response.data?.data ?? response.data;

export const matchesService = {
  getPendingActions: async () => {
    const response = await api.get('/me/matches/pending-actions');
    return payload(response);
  },

  getMatch: async (matchId) => {
    const response = await api.get(`/matches/${matchId}`);
    return payload(response);
  },

  getWorkflow: async (matchId) => {
    const response = await api.get(`/matches/${matchId}/workflow`);
    return payload(response);
  },

  submitResult: async (matchId, result) => {
    const response = await api.post(`/matches/${matchId}/submit-result`, result);
    return response.data;
  },

  confirmResult: async (matchId, comment = null) => {
    const body = comment ? { comment } : {};
    const response = await api.post(`/matches/${matchId}/confirm-result`, body);
    return response.data;
  },
};
