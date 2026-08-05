import api from './api';

export const meService = {
  getRegistrations: async () => {
    const response = await api.get('/me/championship-registrations');
    return response.data.data || response.data; // Adapting to wrapper locally if necessary, assuming response is { data: [...] } or direct
  },

  getMatches: async () => {
    const response = await api.get('/me/matches');
    return response.data.data || response.data;
  },

  getCalendar: async () => {
    const response = await api.get('/me/calendar');
    return response.data.data || response.data;
  },

  getRankings: async () => {
    const response = await api.get('/me/rankings');
    return response.data.data || response.data;
  }
};
