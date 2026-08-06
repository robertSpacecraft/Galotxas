import api from '../../api/client';

const tokenPayload = (token) => ({ token });

export const publicIdentityService = {
  lookup: async (token, { signal } = {}) => {
    const response = await api.post(
      '/public-identity/confirmation/lookup',
      tokenPayload(token),
      { signal },
    );

    return response.data?.data ?? null;
  },

  confirm: async (token) => {
    const response = await api.post(
      '/public-identity/confirmation/confirm',
      tokenPayload(token),
    );

    return response.data;
  },

  deny: async (token) => {
    const response = await api.post(
      '/public-identity/confirmation/deny',
      tokenPayload(token),
    );

    return response.data;
  },
};
