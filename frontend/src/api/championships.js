import api from './api';

/**
 * Championships (Tournaments) API Service
 */
export const championshipsService = {
  /**
   * Get all championships with optional filters
   * @param {Object} filters - season_id, type, status, registration_open
   */
  getChampionships: async (filters = {}) => {
    try {
      const response = await api.get('/championships', { params: filters });
      // Championships now return response.data.data
      return response.data.data;
    } catch (error) {
      console.error('No se han podido cargar los campeonatos.');
      throw error;
    }
  },

  /**
   * Get a single championship by ID or Slug
   * @param {string|number} id - Championship ID or Slug
   */
  getChampionship: async (id) => {
    try {
      const response = await api.get(`/championships/${id}`);
      // Detail now returns response.data.data
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar el campeonato ${id}.`);
      throw error;
    }
  },

  /**
   * Get the ranking for a specific championship (all categories combined)
   * @param {string|number} id - Championship ID
   */
  getChampionshipRanking: async (id) => {
    try {
      const response = await api.get(`/championships/${id}/ranking`);
      // Ranking now returns response.data.data
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar el ranking del campeonato ${id}.`);
      throw error;
    }
  },

  /**
   * Get category details
   */
  getCategory: async (id) => {
    try {
      const response = await api.get(`/categories/${id}`);
      // Category now returns response.data.data
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar la categoría ${id}.`);
      throw error;
    }
  },

  /**
   * Get category standings (ranking)
   */
  getCategoryStandings: async (id) => {
    try {
      const response = await api.get(`/categories/${id}/standings`);
      // Standings now return response.data.data
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar la clasificación de la categoría ${id}.`);
      throw error;
    }
  },

  /**
   * Get category schedule (matches)
   */
  getCategorySchedule: async (id) => {
    try {
      const response = await api.get(`/categories/${id}/schedule`);
      // Schedule now returns response.data.data
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar el calendario de la categoría ${id}.`);
      throw error;
    }
  },

  /**
   * Get all public seasons with their public championships
   */
  getSeasons: async () => {
    try {
      const response = await api.get('/seasons');
      // Seasons now return response.data.data
      return response.data.data;
    } catch (error) {
      console.error('No se han podido cargar las temporadas.');
      throw error;
    }
  },

  /**
   * Get the ranking for a specific season
   * @param {string|number} seasonId
   */
  getSeasonRanking: async (seasonId) => {
    try {
      const response = await api.get(`/seasons/${seasonId}/ranking`);
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar el ranking de la temporada ${seasonId}.`);
      throw error;
    }
  },

  getAllTimeRanking: async () => {
    try {
      const response = await api.get('/rankings/all-time');
      return response.data.data;
    } catch (error) {
      console.error('No se ha podido cargar el ranking histórico.');
      throw error;
    }
  },

  /**
   * Check registration status for a championship
   * @param {string|number} id - Championship ID
   */
  getRegistrationStatus: async (id) => {
    try {
      const response = await api.get(`/championships/${id}/registration`);
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido cargar la inscripción del campeonato ${id}.`);
      throw error;
    }
  },

  /**
   * Register for a championship
   * @param {string|number} id - Championship ID
   */
  registerChampionship: async (id) => {
    try {
      const response = await api.post(`/championships/${id}/register`);
      return response.data.data;
    } catch (error) {
      console.error(`No se ha podido enviar la inscripción al campeonato ${id}.`);
      throw error;
    }
  },

  /**
   * Get my championship registrations
   */
  getMyRegistrations: async () => {
    try {
      const response = await api.get('/me/championship-registrations');
      return response.data.data;
    } catch (error) {
      console.error('No se han podido cargar las inscripciones de la cuenta.');
      throw error;
    }
  }
};
