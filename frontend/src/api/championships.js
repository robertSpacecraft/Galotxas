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
      console.error('Error fetching championships:', error);
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
      console.error(`Error fetching championship ${id}:`, error);
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
      console.error(`Error fetching ranking for championship ${id}:`, error);
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
      console.error(`Error fetching category ${id}:`, error);
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
      console.error(`Error fetching standings for category ${id}:`, error);
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
      console.error(`Error fetching schedule for category ${id}:`, error);
      throw error;
    }
  },

  /**
   * Get all seasons
   * NOTE: Backend returns raw JSON without wrapper for this endpoint
   */
  getSeasons: async () => {
    try {
      const response = await api.get('/seasons');
      // Seasons now return response.data.data
      return response.data.data;
    } catch (error) {
      console.error('Error fetching seasons:', error);
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
      console.error(`Error fetching ranking for season ${seasonId}:`, error);
      throw error;
    }
  },

  /**
   * Get the all-time ranking
   */
  getAllTimeRanking: async () => {
    try {
      const response = await api.get('/rankings/all-time');
      return response.data.data;
    } catch (error) {
      console.error('Error fetching all-time ranking:', error);
      throw error;
    }
  }
};
