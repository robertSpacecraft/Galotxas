import api from './api';

export const cmsService = {
  getPublishedPages: async () => {
    try {
      const response = await api.get('/cms/pages');
      return response.data.data;
    } catch {
      throw new Error('No se ha podido cargar el índice de contenidos.');
    }
  },

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
