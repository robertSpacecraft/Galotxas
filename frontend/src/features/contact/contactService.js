import api from '../../api/client';

const errorMessages = {
  422: 'Revisa los campos indicados.',
  429: 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
  503: 'El formulario de contacto no está disponible.',
};

export class ContactServiceError extends Error {
  constructor(message, { status = null, errors = {}, kind = 'unexpected' } = {}) {
    super(message);
    this.name = 'ContactServiceError';
    this.status = status;
    this.errors = errors;
    this.kind = kind;
  }
}

const normalizeServiceError = (error) => {
  if (!error?.response) {
    return new ContactServiceError(
      'No se ha podido conectar con el servicio de contacto.',
      { kind: 'network' },
    );
  }

  const status = error.response.status;
  const payload = error.response.data;
  const fallback = errorMessages[status] ?? 'No se ha podido procesar la solicitud.';
  const message = typeof payload?.message === 'string' && payload.message.trim()
    ? payload.message
    : fallback;
  const errors = status === 422 && payload?.errors && typeof payload.errors === 'object'
    ? payload.errors
    : {};

  return new ContactServiceError(message, {
    status,
    errors,
    kind: errorMessages[status] ? 'api' : 'unexpected',
  });
};

export const contactService = {
  getConfig: async ({ signal } = {}) => {
    try {
      const response = await api.get('/contact/config', { signal });
      const enabled = response.data?.data?.enabled;

      if (typeof enabled !== 'boolean') {
        throw new ContactServiceError(
          'La configuración de contacto recibida no es válida.',
          { kind: 'unexpected' },
        );
      }

      return { enabled };
    } catch (error) {
      if (error instanceof ContactServiceError) {
        throw error;
      }

      throw normalizeServiceError(error);
    }
  },

  submit: async (payload, { signal } = {}) => {
    try {
      const response = await api.post('/contact-requests', payload, { signal });

      if (response.status !== 201 || response.data?.data?.received !== true) {
        throw new ContactServiceError(
          'La respuesta del servicio de contacto no es válida.',
          { status: response.status ?? null, kind: 'unexpected' },
        );
      }

      return response.data;
    } catch (error) {
      if (error instanceof ContactServiceError) {
        throw error;
      }

      throw normalizeServiceError(error);
    }
  },
};
