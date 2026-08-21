import api from '../../api/client';
import {
  normalizeNewsArticleResponse,
  normalizeNewsListResponse,
} from './newsContract';

export const newsService = {
  getList: async ({ page = 1, signal } = {}) => {
    const response = await api.get('/news', {
      params: { page },
      signal,
    });

    return normalizeNewsListResponse(response.data);
  },

  getBySlug: async (slug, { signal } = {}) => {
    const response = await api.get(`/news/${encodeURIComponent(slug)}`, { signal });

    return normalizeNewsArticleResponse(response.data);
  },
};
