import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../../api/client';
import {
  ContactServiceError,
  contactService,
} from './contactService';

vi.mock('../../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('contactService', () => {
  beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
  });

  it('loads the allowlisted public configuration', async () => {
    const signal = new AbortController().signal;
    api.get.mockResolvedValue({
      data: { message: null, data: { enabled: false } },
    });

    await expect(contactService.getConfig({ signal })).resolves.toEqual({
      enabled: false,
    });
    expect(api.get).toHaveBeenCalledWith('/contact/config', { signal });
  });

  it('rejects an unexpected configuration response', async () => {
    api.get.mockResolvedValue({ data: { data: { enabled: 'false' } } });

    await expect(contactService.getConfig()).rejects.toMatchObject({
      kind: 'unexpected',
    });
  });

  it('returns the receipt envelope for a valid 201 response', async () => {
    const payload = {
      name: 'Persona interesada',
      email: 'persona@example.test',
      subject: 'Consulta',
      message: 'Mensaje suficientemente largo.',
      privacy_accepted: true,
      website: '',
    };
    const envelope = {
      message: 'Tu mensaje se ha recibido correctamente.',
      data: { received: true },
    };
    api.post.mockResolvedValue({ status: 201, data: envelope });

    await expect(contactService.submit(payload)).resolves.toEqual(envelope);
    expect(api.post).toHaveBeenCalledWith('/contact-requests', payload, {
      signal: undefined,
    });
  });

  it.each([
    [422, 'api'],
    [429, 'api'],
    [503, 'api'],
    [500, 'unexpected'],
  ])('normalizes an HTTP %s response', async (status, kind) => {
    api.post.mockRejectedValue({
      response: {
        status,
        data: status === 422
          ? { message: 'Datos inválidos.', errors: { email: ['Correo inválido.'] } }
          : {},
      },
    });

    const error = await contactService.submit({}).catch((caught) => caught);

    expect(error).toBeInstanceOf(ContactServiceError);
    expect(error).toMatchObject({ status, kind });
    expect(error.errors).toEqual(
      status === 422 ? { email: ['Correo inválido.'] } : {},
    );
  });

  it('distinguishes a network failure without exposing Axios internals', async () => {
    api.post.mockRejectedValue(new Error('Network Error'));

    await expect(contactService.submit({})).rejects.toMatchObject({
      status: null,
      kind: 'network',
      message: 'No se ha podido conectar con el servicio de contacto.',
    });
  });

  it('rejects an unexpected successful response shape', async () => {
    api.post.mockResolvedValue({
      status: 200,
      data: { message: null, data: null },
    });

    await expect(contactService.submit({})).rejects.toMatchObject({
      status: 200,
      kind: 'unexpected',
    });
  });
});
