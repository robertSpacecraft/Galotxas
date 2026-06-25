import api from './api';

export const cmsService = {
  getPageBySlug: async (slug) => {
    try {
      const response = await api.get(`/cms/pages/${slug}`);
      return response.data.data;
    } catch (error) {
      if (error.response?.status === 404) {
        const notFoundError = new Error('Página CMS no encontrada.');
        notFoundError.status = 404;
        throw notFoundError;
      }

      throw error;
    }
  },
};
