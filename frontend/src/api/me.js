import api from './api';

export const meService = {
  getRegistrations: async () => {
    try {
      const response = await api.get('/me/championship-registrations');
      return response.data.data || response.data; // Adapting to wrapper locally if necessary, assuming response is { data: [...] } or direct
    } catch (error) {
      console.error('Error fetching registrations:', error);
      throw error;
    }
  },

  getMatches: async () => {
    try {
      const response = await api.get('/me/matches');
      return response.data.data || response.data;
    } catch (error) {
      console.error('Error fetching matches:', error);
      throw error;
    }
  },

  getCalendar: async () => {
    try {
      const response = await api.get('/me/calendar');
      return response.data.data || response.data;
    } catch (error) {
      console.error('Error fetching calendar:', error);
      throw error;
    }
  },

  getRankings: async () => {
    try {
      const response = await api.get('/me/rankings');
      return response.data;
    } catch (error) {
      console.error('Error fetching rankings:', error);
      throw error;
    }
  }
};
